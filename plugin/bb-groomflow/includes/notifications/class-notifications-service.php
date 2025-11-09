<?php
/**
 * Notifications service scaffolding.
 *
 * @package BB_GroomFlow
 */

namespace BBGF\Notifications;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

use BBGF\Plugin;
use wpdb;
use WP_Error;

/**
 * Handles stage-triggered notification scaffolding.
 */
class Notifications_Service {
	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Cached table names.
	 *
	 * @var array<string,string>
	 */
	private array $tables;

	/**
	 * Tracks the most recent wp_mail failure for logging.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $last_mail_error = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->wpdb   = $plugin->get_wpdb();
		$this->tables = $plugin->get_table_names();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'bbgf_visit_stage_changed', array( $this, 'handle_stage_change' ), 10, 4 );
		add_action( 'wp_mail_failed', array( $this, 'track_mail_failure' ) );
	}

	/**
	 * Handle visit stage transitions and queue notifications when enabled.
	 *
	 * @param array  $visit   Visit payload.
	 * @param string $from    Previous stage key.
	 * @param string $to      Destination stage key.
	 * @param array  $context Supplemental context (comment, user_id, timestamp).
	 * @return void
	 */
	public function handle_stage_change( array $visit, string $from, string $to, array $context = array() ): void {
		if ( ! $this->notifications_enabled() ) {
			return;
		}

		$to_stage = sanitize_key( $to );
		if ( '' === $to_stage ) {
			return;
		}

		$triggers = $this->get_stage_triggers( $to_stage );
		if ( empty( $triggers ) ) {
			return;
		}

		$payload = array(
			'visit_id'    => (int) ( $visit['id'] ?? 0 ),
			'from_stage'  => sanitize_key( $from ),
			'to_stage'    => $to_stage,
			'trigger_ids' => array_map(
				static function ( $trigger ) {
					return (int) ( $trigger['notification_id'] ?? 0 );
				},
				$triggers
			),
			'context'     => $context,
			'settings'    => $this->get_email_settings(),
		);

		/**
		 * Fires when a stage change has queued notification work.
		 *
		 * @param array $payload Notification payload data.
		 * @param array $visit   Visit data snapshot.
	 */
		do_action( 'bbgf_notification_stage_queued', $payload, $visit );

		$this->dispatch_stage_notifications( $visit, $to_stage, $triggers, $context );
	}

	/**
	 * Remember the most recent mail failure so we can log details.
	 *
	 * @param WP_Error $error Error information from wp_mail_failed.
	 * @return void
	 */
	public function track_mail_failure( WP_Error $error ): void {
		$this->last_mail_error = $error;
	}

	/**
	 * Resend a notification based on an existing log entry.
	 *
	 * @param int $log_id Log ID to resend.
	 * @return bool|WP_Error
	 */
	public function resend_notification( int $log_id ) {
		if ( $log_id <= 0 || empty( $this->tables['notification_logs'] ) ) {
			return new WP_Error( 'bbgf_resend_invalid_log', __( 'Invalid notification log reference.', 'bb-groomflow' ) );
		}

		$logs_table = $this->tables['notification_logs'];

		$log = $this->wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->prepare( "SELECT * FROM {$logs_table} WHERE id = %d", $log_id ),
			ARRAY_A
		);

		if ( empty( $log ) ) {
			return new WP_Error( 'bbgf_resend_missing_log', __( 'Notification log entry not found.', 'bb-groomflow' ) );
		}

		$notification_id = isset( $log['notification_id'] ) ? (int) $log['notification_id'] : 0;
		if ( $notification_id <= 0 ) {
			return new WP_Error( 'bbgf_resend_missing_notification', __( 'Notification template unavailable for resend.', 'bb-groomflow' ) );
		}

		$notification = $this->get_notification_row( $notification_id );
		if ( empty( $notification ) ) {
			return new WP_Error( 'bbgf_resend_missing_notification', __( 'Notification template unavailable for resend.', 'bb-groomflow' ) );
		}

		// phpcs:disable WordPress.WhiteSpace.OperatorSpacing,WordPress.Arrays.MultipleStatementAlignment
		$trigger = array();
		$trigger_id = isset( $log['trigger_id'] ) ? (int) $log['trigger_id'] : 0;
		if ( $trigger_id > 0 ) {
			$trigger      = $this->get_trigger_row( $trigger_id );
		}

		$visit_id = isset( $log['visit_id'] ) ? (int) $log['visit_id'] : 0;
		$visit = $this->get_visit_payload( $visit_id );

		$stage = sanitize_key( $log['stage'] ?? '' );
		$settings = $this->get_email_settings();

		$tokens = $this->collect_tokens( $visit, $stage, array() );
		$subject = $this->render_subject( (string) ( $notification['subject'] ?? '' ), $settings, $tokens );
		$html = $this->render_body_html( (string) ( $notification['body_html'] ?? '' ), $tokens, $notification );
		$text = $this->render_body_text( (string) ( $notification['body_text'] ?? '' ), $tokens, $notification );

		$recipient_list = array();

		if ( ! empty( $trigger ) ) {
			$recipient_list = $this->resolve_recipients( $visit, $trigger );
		}

		if ( empty( $recipient_list ) ) {
			$recipient_list = $this->parse_recipient_list( (string) ( $log['recipients'] ?? '' ) );
		}

		if ( empty( $recipient_list ) ) {
			return new WP_Error( 'bbgf_resend_no_recipients', __( 'No recipients available for resend.', 'bb-groomflow' ) );
		}

		$this->last_mail_error = null;
		$sent = $this->send_email( $recipient_list, $subject, $html, $text, $settings, $visit, $trigger );

		$error_message = '';
		if ( ! $sent && $this->last_mail_error instanceof WP_Error ) {
			$error_message = implode( ' | ', array_filter( $this->last_mail_error->get_error_messages() ) );
		}

		// phpcs:enable WordPress.WhiteSpace.OperatorSpacing,WordPress.Arrays.MultipleStatementAlignment

		$log_trigger = $trigger;
		$log_trigger['notification_id'] = $notification_id;
		$log_trigger['channel'] = $notification['channel'] ?? 'email';

		// phpcs:enable WordPress.WhiteSpace.OperatorSpacing,WordPress.Arrays.MultipleStatementAlignment
		$this->log_notification_delivery(
			$visit,
			$stage,
			$log_trigger,
			$recipient_list,
			$subject,
			(bool) $sent,
			$error_message
		);

		return (bool) $sent;
	}

	/**
	 * Determine if stage notifications are enabled.
	 *
	 * @return bool
	 */
	private function notifications_enabled(): bool {
		$settings = $this->plugin->get_settings();

		return ! empty( $settings['notifications']['enable_stage_notifications'] );
	}

	/**
	 * Retrieve triggers for the specified stage, including template data.
	 *
	 * @param string $stage Stage key.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_stage_triggers( string $stage ): array {
		if ( ! isset( $this->tables['notification_triggers'] ) || ! isset( $this->tables['notifications'] ) ) {
			return array();
		}

		$sql = sprintf(
			'SELECT t.id, t.notification_id, t.recipient_type, t.recipient_email, n.name, n.channel, n.subject, n.body_html, n.body_text FROM %1$s AS t LEFT JOIN %2$s AS n ON n.id = t.notification_id WHERE t.trigger_stage = %%s AND t.enabled = 1 AND n.id IS NOT NULL',
			$this->tables['notification_triggers'],
			$this->tables['notifications']
		);

		$query = $this->wpdb->prepare( $sql, $stage );
		$rows  = $this->wpdb->get_results( $query, ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				$row['recipient_type']  = $row['recipient_type'] ?? 'guardian_primary';
				$row['recipient_email'] = $row['recipient_email'] ?? '';
				return $row;
			},
			$rows
		);
	}

	/**
	 * Collect email settings for downstream handlers.
	 *
	 * @return array<string,string>
	 */
	private function get_email_settings(): array {
		$settings = $this->plugin->get_settings();

		return array(
			'from_name'      => isset( $settings['notifications']['from_name'] ) ? (string) $settings['notifications']['from_name'] : '',
			'from_email'     => isset( $settings['notifications']['from_email'] ) ? (string) $settings['notifications']['from_email'] : '',
			'subject_prefix' => isset( $settings['notifications']['subject_prefix'] ) ? (string) $settings['notifications']['subject_prefix'] : '',
		);
	}

	/**
	 * Send notifications for a stage change.
	 *
	 * @param array  $visit    Visit data.
	 * @param string $stage    New stage key.
	 * @param array  $triggers Trigger rows with template data.
	 * @param array  $context  Additional context.
	 * @return void
	 */
	private function dispatch_stage_notifications( array $visit, string $stage, array $triggers, array $context ): void {
		$settings = $this->get_email_settings();

		foreach ( $triggers as $trigger ) {
			$recipients = $this->resolve_recipients( $visit, $trigger );
			if ( empty( $recipients ) ) {
				continue;
			}

			$tokens  = $this->collect_tokens( $visit, $stage, $context );
			$subject = $this->render_subject( (string) ( $trigger['subject'] ?? '' ), $settings, $tokens );
			$html    = $this->render_body_html( (string) ( $trigger['body_html'] ?? '' ), $tokens, $trigger );
			$text    = $this->render_body_text( (string) ( $trigger['body_text'] ?? '' ), $tokens, $trigger );

			if ( '' === $html && '' === $text ) {
				continue;
			}

			$this->last_mail_error = null;

			$sent = $this->send_email( $recipients, $subject, $html, $text, $settings, $visit, $trigger );

			$error_message = '';
			if ( ! $sent && $this->last_mail_error instanceof WP_Error ) {
				$error_message = implode( ' | ', array_filter( $this->last_mail_error->get_error_messages() ) );
			}

			$this->log_notification_delivery(
				$visit,
				$stage,
				$trigger,
				$recipients,
				$subject,
				$sent,
				$error_message
			);
		}
	}

	/**
	 * Resolve recipient list based on trigger configuration.
	 *
	 * @param array $visit   Visit payload.
	 * @param array $trigger Trigger row.
	 * @return array<int,string> Sanitized email addresses.
	 */
	private function resolve_recipients( array $visit, array $trigger ): array {
		$type              = $trigger['recipient_type'] ?? 'guardian_primary';
		$email             = trim( (string) ( $trigger['recipient_email'] ?? '' ) );
		$list              = array();
		$guardian_email    = sanitize_email( $visit['guardian']['email'] ?? '' );
		$custom_recipients = $this->parse_custom_recipients( $email );

		switch ( $type ) {
			case 'custom_email':
				$list = $custom_recipients;
				break;
			case 'guardian_primary_and_custom':
				if ( $guardian_email ) {
					$list[] = $guardian_email;
				}
				$list = array_merge( $list, $custom_recipients );
				break;
			case 'guardian_primary':
			default:
				if ( $guardian_email ) {
					$list[] = $guardian_email;
				}
				break;
		}

		$list = array_unique( array_filter( $list ) );

		/**
		 * Filtered recipient addresses.
		 *
		 * @var string[]
		 */
		$filtered = apply_filters( 'bbgf_notification_recipients', array_values( array_filter( $list ) ), $visit, $trigger );

		return array_values( array_filter( $filtered ) );
	}

	/**
	 * Convert a raw custom recipient string into sanitized email addresses.
	 *
	 * @param string $raw_emails Raw email string.
	 * @return array<int,string>
	 */
	private function parse_custom_recipients( string $raw_emails ): array {
		if ( '' === trim( $raw_emails ) ) {
			return array();
		}

		$normalized = str_replace(
			array( "\r\n", "\r", "\n", ';' ),
			array( ',', ',', ',', ',' ),
			$raw_emails
		);

		$parts  = array_filter( array_map( 'trim', explode( ',', $normalized ) ) );
		$emails = array();

		foreach ( $parts as $part ) {
			$validated = sanitize_email( $part );
			if ( '' !== $validated ) {
				$emails[ $validated ] = $validated;
			}
		}

		return array_values( $emails );
	}

	/**
	 * Parse a comma-separated recipient list from logs.
	 *
	 * @param string $recipients Raw recipient string.
	 * @return array<int,string>
	 */
	private function parse_recipient_list( string $recipients ): array {
		if ( '' === trim( $recipients ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_email', array_map( 'trim', explode( ',', $recipients ) ) )
			)
		);
	}

	/**
	 * Fetch notification row by ID.
	 *
	 * @param int $notification_id Notification ID.
	 * @return array<string,mixed>
	 */
	private function get_notification_row( int $notification_id ): array {
		if ( empty( $this->tables['notifications'] ) ) {
			return array();
		}

		return (array) $this->wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->prepare(
				"SELECT id, channel, subject, body_html, body_text FROM {$this->tables['notifications']} WHERE id = %d",
				$notification_id
			),
			ARRAY_A
		);
	}

	/**
	 * Fetch trigger row by ID.
	 *
	 * @param int $trigger_id Trigger ID.
	 * @return array<string,mixed>
	 */
	private function get_trigger_row( int $trigger_id ): array {
		if ( empty( $this->tables['notification_triggers'] ) ) {
			return array();
		}

		return (array) $this->wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->prepare(
				"SELECT id, trigger_stage, notification_id, recipient_type, recipient_email, enabled FROM {$this->tables['notification_triggers']} WHERE id = %d",
				$trigger_id
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve visit context for token rendering.
	 *
	 * @param int $visit_id Visit ID.
	 * @return array<string,mixed>
	 */
	private function get_visit_payload( int $visit_id ): array {
		if ( $visit_id <= 0 || empty( $this->tables['visits'] ) ) {
			return array();
		}

		$visits_table    = $this->tables['visits'];
		$clients_table   = $this->tables['clients'] ?? '';
		$guardians_table = $this->tables['guardians'] ?? '';

		$sql = "SELECT v.id, v.current_stage,
			c.name AS client_name,
			g.first_name AS guardian_first_name,
			g.last_name AS guardian_last_name,
			g.email AS guardian_email
			FROM {$visits_table} AS v";

		if ( '' !== $clients_table ) {
			$sql .= " LEFT JOIN {$clients_table} AS c ON c.id = v.client_id";
		}

		if ( '' !== $guardians_table ) {
			$sql .= " LEFT JOIN {$guardians_table} AS g ON g.id = v.guardian_id";
		}

		$sql .= ' WHERE v.id = %d';

		$row = $this->wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->prepare( $sql, $visit_id ),
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return array();
		}

		return array(
			'id'            => (int) $row['id'],
			'client'        => array(
				'name' => (string) ( $row['client_name'] ?? '' ),
			),
			'guardian'      => array(
				'first_name' => (string) ( $row['guardian_first_name'] ?? '' ),
				'last_name'  => (string) ( $row['guardian_last_name'] ?? '' ),
				'email'      => (string) ( $row['guardian_email'] ?? '' ),
			),
			'current_stage' => (string) ( $row['current_stage'] ?? '' ),
		);
	}

	/**
	 * Collect token replacements for templates.
	 *
	 * @param array  $visit   Visit payload.
	 * @param string $stage   Destination stage.
	 * @param array  $context Additional context.
	 * @return array<string,string>
	 */
	private function collect_tokens( array $visit, string $stage, array $context ): array {
		$client   = $visit['client'] ?? array();
		$guardian = $visit['guardian'] ?? array();

		$tokens = array(
			'{{client_name}}'         => (string) ( $client['name'] ?? '' ),
			'{{guardian_first_name}}' => (string) ( $guardian['first_name'] ?? '' ),
			'{{guardian_last_name}}'  => (string) ( $guardian['last_name'] ?? '' ),
			'{{guardian_full_name}}'  => trim( ( $guardian['first_name'] ?? '' ) . ' ' . ( $guardian['last_name'] ?? '' ) ),
			'{{guardian_email}}'      => (string) ( $guardian['email'] ?? '' ),
			'{{visit_stage}}'         => $stage,
			'{{visit_comment}}'       => trim( (string) ( $context['comment'] ?? '' ) ),
			'{{visit_id}}'            => (string) ( $visit['id'] ?? '' ),
			'{{site_name}}'           => get_bloginfo( 'name' ),
		);

		return apply_filters( 'bbgf_notification_tokens', $tokens, $visit, $stage, $context );
	}

	/**
	 * Render subject with prefix and tokens.
	 *
	 * @param string $subject_template Template subject.
	 * @param array  $settings         Email settings.
	 * @param array  $tokens           Token map.
	 * @return string
	 */
	private function render_subject( string $subject_template, array $settings, array $tokens ): string {
		$subject = trim( strtr( $subject_template, $tokens ) );
		$prefix  = trim( (string) ( $settings['subject_prefix'] ?? '' ) );

		if ( '' !== $prefix ) {
			$subject = sprintf( '[%s] %s', $prefix, $subject );
		}

		return '' !== $subject ? $subject : __( 'GroomFlow update', 'bb-groomflow' );
	}

	/**
	 * Render HTML body.
	 *
	 * @param string $template Template markup.
	 * @param array  $tokens   Token map.
	 * @param array  $trigger  Trigger data.
	 * @return string
	 */
	private function render_body_html( string $template, array $tokens, array $trigger ): string {
		$content = trim( strtr( $template, $tokens ) );
		if ( '' === $content && ! empty( $trigger['body_text'] ) ) {
			$content = nl2br( strtr( (string) $trigger['body_text'], $tokens ) );
		}

		return $content;
	}

	/**
	 * Render plain text body.
	 *
	 * @param string $template Template text.
	 * @param array  $tokens   Token map.
	 * @param array  $trigger  Trigger data.
	 * @return string
	 */
	private function render_body_text( string $template, array $tokens, array $trigger ): string {
		$content = trim( strtr( $template, $tokens ) );
		if ( '' === $content && ! empty( $trigger['body_html'] ) ) {
			$content = wp_strip_all_tags( strtr( (string) $trigger['body_html'], $tokens ) );
		}

		return $content;
	}

	/**
	 * Send the notification email.
	 *
	 * @param array  $recipients Recipients list.
	 * @param string $subject    Email subject.
	 * @param string $html       HTML body.
	 * @param string $text       Plain text body.
	 * @param array  $settings   Email settings.
	 * @param array  $visit      Visit data.
	 * @param array  $trigger    Trigger data.
	 * @return bool
	 */
	private function send_email( array $recipients, string $subject, string $html, string $text, array $settings, array $visit, array $trigger ): bool {
		if ( empty( $recipients ) ) {
			return false;
		}

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$from      = sanitize_email( $settings['from_email'] ?? '' );
		$from_name = $settings['from_name'] ?? '';

		if ( $from ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name ? sanitize_text_field( $from_name ) : get_bloginfo( 'name' ), $from );
		}

		$message = '' !== $html ? $html : nl2br( esc_html( $text ) );

		$email = apply_filters(
			'bbgf_notification_email_before_send',
			array(
				'recipients' => $recipients,
				'subject'    => $subject,
				'message'    => $message,
				'headers'    => $headers,
			),
			$visit,
			$trigger
		);

		if ( empty( $email['recipients'] ) || empty( $email['subject'] ) || empty( $email['message'] ) ) {
			return false;
		}

		$sent = wp_mail( (array) $email['recipients'], (string) $email['subject'], (string) $email['message'], (array) $email['headers'] );

		do_action( 'bbgf_notification_email_sent', $sent, $email, $visit, $trigger );

		return (bool) $sent;
	}

	/**
	 * Persist notification delivery metadata for auditing.
	 *
	 * @param array  $visit         Visit payload.
	 * @param string $stage         Destination stage.
	 * @param array  $trigger       Trigger row.
	 * @param array  $recipients    Recipient list.
	 * @param string $subject       Email subject.
	 * @param bool   $sent          Whether the delivery was reported as successful.
	 * @param string $error_message Error details when a delivery fails.
	 * @return void
	 */
	private function log_notification_delivery( array $visit, string $stage, array $trigger, array $recipients, string $subject, bool $sent, string $error_message ): void {
		if ( empty( $this->tables['notification_logs'] ) ) {
			return;
		}

		$table = $this->tables['notification_logs'];
		$now   = $this->plugin->now();

		$recipient_string = implode(
			', ',
			array_filter(
				array_map( 'sanitize_email', $recipients )
			)
		);

		$this->wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'notification_id' => isset( $trigger['notification_id'] ) ? (int) $trigger['notification_id'] : null,
				'trigger_id'      => isset( $trigger['id'] ) ? (int) $trigger['id'] : null,
				'visit_id'        => isset( $visit['id'] ) ? (int) $visit['id'] : null,
				'stage'           => sanitize_key( $stage ),
				'channel'         => isset( $trigger['channel'] ) ? sanitize_key( (string) $trigger['channel'] ) : 'email',
				'recipients'      => $recipient_string,
				'subject'         => wp_strip_all_tags( $subject ),
				'status'          => $sent ? 'sent' : 'failed',
				'error_message'   => $sent ? '' : wp_strip_all_tags( $error_message ),
				'sent_at'         => $now,
				'created_at'      => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
