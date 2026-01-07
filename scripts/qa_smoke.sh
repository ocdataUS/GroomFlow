#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ARTIFACTS_DIR="${ARTIFACTS_DIR:-/opt/qa/artifacts}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_PATH="${ARTIFACTS_DIR}/qa-smoke-${TIMESTAMP}.txt"
PATH="${ROOT_DIR}/scripts:${PATH}"

mkdir -p "${ARTIFACTS_DIR}"

log() {
	echo "$@" | tee -a "${LOG_PATH}"
}

run_wp() {
	( cd "${ROOT_DIR}/docker" && docker compose run --rm -T wpcli "$@" )
}

latest_zip() {
	ls -1t "${ROOT_DIR}"/build/bb-groomflow-*.zip 2>/dev/null | head -n 1
}

log "== GroomFlow QA smoke =="
log "UTC: ${TIMESTAMP}"
log "Artifacts dir: ${ARTIFACTS_DIR}"

if [[ "${SKIP_ASSET_BUILD:-0}" != "1" ]]; then
	log "Building assets via npm run build..."
	( cd "${ROOT_DIR}" && npm run build ) | tee -a "${LOG_PATH}"
else
	log "Skipping asset build (SKIP_ASSET_BUILD=1)."
fi

log "Packaging plugin ZIP..."
(
	cd "${ROOT_DIR}"
	bash scripts/build_plugin_zip.sh
) | tee -a "${LOG_PATH}"

ZIP_PATH="$(latest_zip)"
if [[ -z "${ZIP_PATH}" ]]; then
	log "ERROR: No ZIP found in build/ after packaging." >&2
	exit 1
fi
log "Using ZIP: ${ZIP_PATH}"

log "Starting Docker stack..."
( cd "${ROOT_DIR}/docker" && docker compose up -d ) | tee -a "${LOG_PATH}"

log "Installing plugin ZIP into Docker WordPress..."
(
	cd "${ROOT_DIR}/docker"
	docker compose cp "${ZIP_PATH}" wordpress:/var/www/html/bb-groomflow.zip
	docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
	docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force
) | tee -a "${LOG_PATH}"

log "Running PHPCS..."
qa-phpcs "${ROOT_DIR}/plugin/bb-groomflow" | tee -a "${LOG_PATH}"
PHPCS_ARTIFACT="$(ls -1t "${ARTIFACTS_DIR}"/phpcs-* 2>/dev/null | head -n 1 || true)"
if [[ -n "${PHPCS_ARTIFACT}" ]]; then
	log "PHPCS artifact: ${PHPCS_ARTIFACT}"
fi

read -r -d '' BOARD_SCRIPT <<'PHP' || true
$user = get_user_by( 'login', 'codexadmin' );
wp_set_current_user( $user ? $user->ID : 1 );

$visit_service = bbgf()->visit_service();
$view          = $visit_service->get_default_view();
if ( null === $view ) {
	fwrite( STDERR, "No default view available.\n" );
	exit( 1 );
}

$payload = $visit_service->build_board_payload(
	array(
		'view'           => $view,
		'modified_after' => '',
		'stages'         => array(),
		'mask_guardian'  => false,
		'mask_sensitive' => false,
		'readonly'       => false,
		'is_public'      => false,
	)
);
$stages  = $payload['stages'] ?? array();
$counts  = array();

foreach ( $stages as $stage ) {
	$key            = $stage['key'] ?? '';
	$counts[ $key ] = count( $stage['visits'] ?? array() );
}

echo wp_json_encode(
	array(
		'view'         => $payload['view'] ?? array(),
		'last_updated' => $payload['last_updated'] ?? '',
		'stage_counts' => $counts,
	)
);
PHP

log "Board payload smoke..."
run_wp wp eval "${BOARD_SCRIPT}" | tee -a "${LOG_PATH}"

read -r -d '' MOVE_SCRIPT <<'PHP' || true
$user = get_user_by( 'login', 'codexadmin' );
wp_set_current_user( $user ? $user->ID : 1 );

$plugin  = bbgf();
$service = $plugin->visit_service();
$view    = $service->get_default_view();
if ( null === $view ) {
	fwrite( STDERR, "No default view available.\n" );
	exit( 1 );
}

$payload = $service->build_board_payload(
	array(
		'view'           => $view,
		'modified_after' => '',
		'stages'         => array(),
		'mask_guardian'  => false,
		'mask_sensitive' => false,
		'readonly'       => false,
		'is_public'      => false,
	)
);
$stages  = $payload['stages'] ?? array();

$candidate = array(
	'visit_id' => 0,
	'from'     => '',
	'to'       => '',
);

$stage_count = count( $stages );
for ( $i = 0; $i < $stage_count - 1; $i++ ) {
	$stage_visits = $stages[ $i ]['visits'] ?? array();
	if ( empty( $stage_visits ) ) {
		continue;
	}

	$candidate = array(
		'visit_id' => (int) ( $stage_visits[0]['id'] ?? 0 ),
		'from'     => (string) ( $stages[ $i ]['key'] ?? '' ),
		'to'       => (string) ( $stages[ $i + 1 ]['key'] ?? '' ),
	);
	break;
}

if ( $candidate['visit_id'] <= 0 || '' === $candidate['to'] ) {
	fwrite( STDERR, "No visit available for stage move.\n" );
	exit( 1 );
}

$settings = $plugin->get_settings();
$settings['notifications']['enable_stage_notifications'] = 1;
if ( empty( $settings['notifications']['from_email'] ) ) {
	$settings['notifications']['from_email'] = get_option( 'admin_email' );
}
if ( empty( $settings['notifications']['from_name'] ) ) {
	$settings['notifications']['from_name'] = get_bloginfo( 'name' );
}
update_option( 'bbgf_settings', $settings );

global $wpdb;
$tables            = $plugin->get_table_names();
$now               = $plugin->now();
$notifications_key = $tables['notifications'];
$triggers_key      = $tables['notification_triggers'];
$logs_key          = $tables['notification_logs'];

$notification_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$notifications_key} WHERE name = %s LIMIT 1",
		'QA Ready Notice'
	)
);

if ( 0 === $notification_id ) {
	$wpdb->insert(
		$notifications_key,
		array(
			'name'       => 'QA Ready Notice',
			'channel'    => 'email',
			'subject'    => 'GroomFlow QA notification',
			'body_html'  => '<strong>{{client_name}}</strong> moved to {{visit_stage}} at {{site_name}}.',
			'body_text'  => '{{client_name}} moved to {{visit_stage}} at {{site_name}}.',
			'created_at' => $now,
			'updated_at' => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	$notification_id = (int) $wpdb->insert_id;
}

$trigger_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$triggers_key} WHERE trigger_stage = %s AND notification_id = %d LIMIT 1",
		$candidate['to'],
		$notification_id
	)
);

if ( 0 === $trigger_id ) {
	$wpdb->insert(
		$triggers_key,
		array(
			'trigger_stage'   => $candidate['to'],
			'notification_id' => $notification_id,
			'enabled'         => 1,
			'recipient_type'  => 'custom_email',
			'recipient_email' => 'qa@example.com',
			'conditions'      => '{}',
			'created_at'      => $now,
			'updated_at'      => $now,
		),
		array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	$trigger_id = (int) $wpdb->insert_id;
}

$result = $service->move_visit(
	$candidate['visit_id'],
	$candidate['to'],
	'QA smoke stage move',
	get_current_user_id()
);

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . PHP_EOL );
	exit( 1 );
}

$log_id = (int) $wpdb->get_var( "SELECT id FROM {$logs_key} ORDER BY id DESC LIMIT 1" );
$resend = null;
if ( $log_id > 0 ) {
	$resend = $plugin->notifications_service()->resend_notification( $log_id );
	if ( is_wp_error( $resend ) ) {
		$resend = array(
			'error' => $resend->get_error_message(),
		);
	}
}

echo wp_json_encode(
	array(
		'candidate'   => $candidate,
		'move_status' => is_wp_error( $result ) ? $result->get_error_message() : 'ok',
		'visit_stage' => $result['visit']['current_stage'] ?? '',
		'history_id'  => isset( $result['history_entry']['id'] ) ? (int) $result['history_entry']['id'] : null,
		'log_id'      => $log_id ?: null,
		'resend'      => $resend,
	)
);
PHP

log "Stage move + notification smoke..."
run_wp wp eval "${MOVE_SCRIPT}" | tee -a "${LOG_PATH}"

read -r -d '' STATS_SCRIPT <<'PHP' || true
$user = get_user_by( 'login', 'codexadmin' );
wp_set_current_user( $user ? $user->ID : 1 );

$routes  = array(
	'daily'        => new WP_REST_Request( 'GET', '/bb-groomflow/v1/stats/daily' ),
	'stage_averages' => new WP_REST_Request( 'GET', '/bb-groomflow/v1/stats/stage-averages' ),
	'service_mix'  => new WP_REST_Request( 'GET', '/bb-groomflow/v1/stats/service-mix' ),
);
$summaries = array();

foreach ( $routes as $key => $request ) {
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) || $response->is_error() ) {
		$error = is_wp_error( $response ) ? $response : $response->as_error();
		$summaries[ $key ] = array(
			'error'  => $error->get_error_message(),
			'status' => $error->get_error_code(),
		);
		continue;
	}

	$data                = $response->get_data();
	$summaries[ $key ] = array(
		'status'        => $response->get_status(),
		'summary'       => $data['summary'] ?? null,
		'stage_samples' => isset( $data['stages'] ) ? count( (array) $data['stages'] ) : null,
		'service_samples' => isset( $data['services'] ) ? count( (array) $data['services'] ) : null,
		'package_samples' => isset( $data['packages'] ) ? count( (array) $data['packages'] ) : null,
	);
}

echo wp_json_encode( $summaries );
PHP

log "Stats endpoint smoke..."
run_wp wp eval "${STATS_SCRIPT}" | tee -a "${LOG_PATH}"

log "QA smoke complete. Log saved to ${LOG_PATH}"
