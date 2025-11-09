import './style.scss';

const settings = window.bbgfBoardSettings || {};

const strings = {
	loading: settings.strings?.loading ?? 'Loading GroomFlow board…',
	emptyColumn: settings.strings?.emptyColumn ?? 'No visits in this stage yet',
	noVisits: settings.strings?.noVisits ?? 'No visits available.',
	refresh: settings.strings?.refresh ?? 'Refresh',
	lastUpdated: settings.strings?.lastUpdated ?? 'Last updated',
	viewSwitcher: settings.strings?.viewSwitcher ?? 'Board views',
	services: settings.strings?.services ?? 'Services',
	flags: settings.strings?.flags ?? 'Behavior flags',
	notes: settings.strings?.notes ?? 'Notes',
	checkIn: settings.strings?.checkIn ?? 'Check-in',
	movePrev: settings.strings?.movePrev ?? 'Back',
	moveNext: settings.strings?.moveNext ?? 'Next',
	unknownClient: settings.strings?.unknownClient ?? 'Client',
	stageControls: settings.strings?.stageControls ?? 'Stage controls',
	loadingError: settings.strings?.loadingError ?? 'Unable to load the board. Please refresh.',
	modalTitle: settings.strings?.modalTitle ?? 'Visit details',
	modalLoading: settings.strings?.modalLoading ?? 'Loading visit…',
	modalClose: settings.strings?.modalClose ?? 'Close',
	modalReadOnly: settings.strings?.modalReadOnly ?? 'Read-only view',
	modalSummary: settings.strings?.modalSummary ?? 'Summary',
	modalNotes: settings.strings?.modalNotes ?? 'Notes',
	modalServices: settings.strings?.modalServices ?? 'Services',
	modalHistory: settings.strings?.modalHistory ?? 'History',
	modalPhotos: settings.strings?.modalPhotos ?? 'Photos',
	modalSave: settings.strings?.modalSave ?? 'Save changes',
	modalSaving: settings.strings?.modalSaving ?? 'Saving…',
	modalNoHistory: settings.strings?.modalNoHistory ?? 'No history recorded yet.',
	modalNoPhotos: settings.strings?.modalNoPhotos ?? 'No photos uploaded for this visit.',
	searchPlaceholder: settings.strings?.searchPlaceholder ?? 'Search clients, guardians, services…',
	moveSuccess: settings.strings?.moveSuccess ?? 'Visit moved.',
	fullscreen: settings.strings?.fullscreen ?? 'Fullscreen',
	exitFullscreen: settings.strings?.exitFullscreen ?? 'Exit fullscreen',
	autoRefresh: settings.strings?.autoRefresh ?? 'Auto-refresh in',
	maskedGuardian: settings.strings?.maskedGuardian ?? 'Guardian hidden for lobby view',
	errorFetching: settings.strings?.errorFetching ?? 'Unable to refresh the board. Please try again.',
};

const presentation = settings.presentation || {};
const ensureArray = (value) => (Array.isArray(value) ? value : []);
const safeString = (value) => (typeof value === 'string' ? value : '');
const toNumber = (value) => {
	const parsed = Number(value);
	return Number.isFinite(parsed) ? parsed : null;
};

const placeholderPhotos = ensureArray(settings.placeholders?.photos)
	.filter((url) => typeof url === 'string' && url.length > 0);

const resolvePresentationConfig = (board) => {
	const modeCandidate = safeString(presentation.mode ?? '');
	let mode = modeCandidate === 'display' || modeCandidate === 'interactive' ? modeCandidate : '';

	if (!mode) {
		mode = board.readonly || board.is_public ? 'display' : 'interactive';
	}

	const pickFlag = (key, fallback) => {
		const value = presentation[key];
		if (typeof value === 'boolean') {
			return value;
		}
		return fallback;
	};

	const defaultMaskBadge = mode === 'display' ? false : Boolean(board.visibility?.mask_guardian);
	const viewSwitcherValue = safeString(presentation.view_switcher ?? '');
	const allowSwitcher = board?.view?.allow_switcher !== false;
	let viewSwitcher = ['dropdown', 'buttons', 'none'].includes(viewSwitcherValue)
		? viewSwitcherValue
		: mode === 'display'
			? 'none'
			: 'dropdown';

	if (!allowSwitcher || mode === 'display') {
		viewSwitcher = 'none';
	}

	return {
		mode,
		showToolbar: pickFlag('show_toolbar', mode !== 'display'),
		showSearch: pickFlag('show_search', mode !== 'display'),
		showFilters: pickFlag('show_filters', mode !== 'display'),
		showRefresh: pickFlag('show_refresh', mode !== 'display'),
		showLastUpdated: pickFlag('show_last_updated', mode !== 'display'),
		showCountdown: pickFlag('show_countdown', mode !== 'display'),
		showFullscreen: pickFlag('show_fullscreen', true),
		showMaskBadge: pickFlag('show_mask_badge', defaultMaskBadge),
		showNotes: pickFlag('show_notes', mode !== 'display'),
		viewSwitcher,
	};
};

const cloneVisit = (visit) => (visit && typeof visit === 'object' ? JSON.parse(JSON.stringify(visit)) : visit);
const cloneStage = (stage) => {
	if (!stage || typeof stage !== 'object') {
		return stage;
	}

	return {
		...stage,
		visits: ensureArray(stage.visits).map((visit) => cloneVisit(visit)),
	};
};

const buildStageMap = (stages) => {
	const map = new Map();

	ensureArray(stages).forEach((stage) => {
		const key = safeString(stage?.key ?? stage?.stage_key);
		if (!key) {
			return;
		}

		map.set(key, cloneStage(stage));
	});

	return map;
};

const applyBoardPatch = (currentBoard, patchBoard) => {
	if (!patchBoard || typeof patchBoard !== 'object') {
		return currentBoard;
	}

	if (!currentBoard || typeof currentBoard !== 'object') {
		return patchBoard;
	}

	const currentStageMap = buildStageMap(currentBoard.stages);
	const patchStageMap = buildStageMap(patchBoard.stages);

	if (!patchStageMap.size) {
		return {
			...currentBoard,
			...patchBoard,
			stages: ensureArray(currentBoard.stages).map((stage) => cloneStage(stage)),
		};
	}

	const changedVisitIds = new Set();
	patchStageMap.forEach((stage) => {
		ensureArray(stage.visits).forEach((visit) => {
			if (visit && (visit.id || visit.id === 0)) {
				changedVisitIds.add(Number(visit.id));
			}
		});
	});

	if (changedVisitIds.size) {
		currentStageMap.forEach((stage, key) => {
			if (!stage) {
				return;
			}

			const filtered = ensureArray(stage.visits).filter((visit) => {
				if (!visit || (visit.id === undefined || visit.id === null)) {
					return true;
				}

				return !changedVisitIds.has(Number(visit.id));
			});

			currentStageMap.set(key, {
				...stage,
				visits: filtered,
			});
		});
	}

	patchStageMap.forEach((stage, key) => {
		const existing = currentStageMap.get(key);
		const mergedStage = {
			...(existing ?? {}),
			...stage,
			visits: ensureArray(stage.visits).map((visit) => cloneVisit(visit)),
		};

		currentStageMap.set(key, mergedStage);
	});

	const mergedStages = Array.from(currentStageMap.values());
	mergedStages.sort((a, b) => {
		const aOrder = toNumber(a?.sort_order) ?? 0;
		const bOrder = toNumber(b?.sort_order) ?? 0;
		return aOrder - bOrder;
	});

	return {
		...currentBoard,
		...patchBoard,
		stages: mergedStages,
	};
};

const findVisitById = (board, visitId) => {
	const normalizedId = Number(visitId);
	if (!board || Number.isNaN(normalizedId)) {
		return null;
	}

	for (const stage of ensureArray(board.stages)) {
		for (const visit of ensureArray(stage.visits)) {
			if (Number(visit?.id) === normalizedId) {
				return {
					stage,
					visit,
				};
			}
		}
	}

	return null;
};

const formatDuration = (totalSeconds) => {
	if (!Number.isFinite(totalSeconds)) {
		return '0s';
	}

	const seconds = Math.max(0, Math.floor(totalSeconds));
	const hours = Math.floor(seconds / 3600);
	const minutes = Math.floor((seconds % 3600) / 60);
	const remainingSeconds = seconds % 60;

	if (hours >= 1) {
		return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
	}

	if (minutes >= 1) {
		return `${minutes}m`;
	}

	return `${remainingSeconds}s`;
};

const formatTime = (isoString) => {
	if (!isoString) {
		return '';
	}

	const date = new Date(isoString);
	if (Number.isNaN(date.getTime())) {
		return '';
	}

	return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
};

const formatDateForLastUpdated = (isoString) => formatTime(isoString);

const formatDateTime = (isoString) => {
	if (!isoString) {
		return '';
	}

	const date = new Date(isoString);
	if (Number.isNaN(date.getTime())) {
		return '';
	}

	return date.toLocaleString([], {
		hour: 'numeric',
		minute: '2-digit',
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	});
};

const humanizeRelativeTime = (isoString) => {
	if (!isoString) {
		return '';
	}

	const target = new Date(isoString);
	if (Number.isNaN(target.getTime())) {
		return '';
	}

	const now = new Date();
	const deltaSeconds = Math.round((now.getTime() - target.getTime()) / 1000);

	if (deltaSeconds < 10) {
		return 'just now';
	}

	if (deltaSeconds < 60) {
		return `${deltaSeconds}s ago`;
	}

	const minutes = Math.round(deltaSeconds / 60);
	if (minutes < 60) {
		return `${minutes} min${minutes === 1 ? '' : 's'} ago`;
	}

	const hours = Math.round(minutes / 60);
	if (hours < 24) {
		return `${hours} hr${hours === 1 ? '' : 's'} ago`;
	}

	const days = Math.round(hours / 24);
	return `${days} day${days === 1 ? '' : 's'} ago`;
};

const getTimerState = (seconds, thresholds = {}) => {
	const yellow = toNumber(thresholds.yellow) ?? 0;
	const red = toNumber(thresholds.red) ?? 0;

	if (red > 0 && seconds >= red) {
		return 'critical';
	}

	if (yellow > 0 && seconds >= yellow) {
		return 'warning';
	}

	return 'on-track';
};

const buildCapacityHint = (capacity = {}, count = 0) => {
	const soft = toNumber(capacity.soft);
	const hard = toNumber(capacity.hard);

	if (Number.isFinite(hard) && count > hard) {
		return 'Hard limit exceeded';
	}

	if (Number.isFinite(soft) && count > soft) {
		return 'At capacity';
	}

	if (Number.isFinite(soft) && soft > 0) {
		const remaining = Math.max(0, soft - count);

		if (remaining > 1) {
			return `${remaining} slots open`;
		}

		if (remaining === 1) {
			return '1 slot open';
		}

		return 'Fully booked';
	}

	return 'Flexible capacity';
};

const extractPrimaryPhoto = (photos) => {
	const list = ensureArray(photos);
	if (!list.length) {
		return null;
	}

	const first = list[0];
	if (!first || typeof first !== 'object') {
		return null;
	}

	if (first.url) {
		return { url: first.url, alt: safeString(first.alt) };
	}

	if (first.sizes) {
		const candidate = first.sizes.thumbnail?.url || first.sizes.medium?.url || first.sizes.full?.url;
		if (candidate) {
			return { url: candidate, alt: safeString(first.alt) };
		}
	}

	return null;
};

const getPlaceholderPhoto = (visit) => {
	if (!placeholderPhotos.length) {
		return null;
	}

	const visitId = Number(visit?.id);
	const index = Number.isFinite(visitId) ? Math.abs(visitId) % placeholderPhotos.length : 0;
	const url = placeholderPhotos[index];
	const clientName = safeString(visit?.client?.name ?? strings.unknownClient);
	return {
		url,
		alt: clientName ? `${clientName} placeholder photo` : strings.unknownClient,
	};
};

const getVisitPhoto = (visit) => extractPrimaryPhoto(visit?.photos) ?? getPlaceholderPhoto(visit);

const buildServiceSummary = (services) =>
	ensureArray(services)
		.map((service) => safeString(service?.name))
		.filter(Boolean)
		.join(', ');

const escapeHtml = (value) => {
	return safeString(value).replace(/[&<>"']/g, (char) => {
		switch (char) {
			case '&':
				return '&amp;';
			case '<':
				return '&lt;';
			case '>':
				return '&gt;';
			case '"':
				return '&quot;';
			case "'":
				return '&#039;';
			default:
				return char;
		}
	});
};

const buildModalFormFromVisit = (visit) => {
 if (!visit || typeof visit !== 'object') {
 	return {
 		instructions: '',
 		publicNotes: '',
 		privateNotes: '',
 		services: [],
 	};
 }

 return {
 	instructions: safeString(visit.instructions ?? ''),
 	publicNotes: safeString(visit.public_notes ?? ''),
 	privateNotes: safeString(visit.private_notes ?? ''),
 	services: ensureArray(visit.services)
 		.map((service) => Number(service?.id))
 		.filter((id) => Number.isFinite(id)),
 };
};

const visitMatchesQuery = (visit, query) => {
	if (!visit) {
		return false;
	}

	const haystack = [
		visit.client?.name,
		visit.client?.breed,
		visit.guardian?.first_name,
		visit.guardian?.last_name,
		visit.guardian?.email,
		visit.guardian?.phone,
		visit.instructions,
		visit.public_notes,
		visit.private_notes,
	]
		.concat(ensureArray(visit.services).map((service) => service?.name))
		.concat(ensureArray(visit.flags).map((flag) => flag?.name))
		.filter(Boolean)
		.map((value) => String(value).toLowerCase());

	return haystack.some((value) => value.includes(query));
};

const getStageNeighbors = (board, stageKey) => {
	const stages = ensureArray(board?.stages);
	const index = stages.findIndex((stage) => stage.key === stageKey);
	if (index === -1) {
		return { prev: null, next: null };
	}

	return {
		prev: index > 0 ? stages[index - 1] : null,
		next: index < stages.length - 1 ? stages[index + 1] : null,
	};
};

const getBoardFilterOptions = (board) => {
	const servicesMap = new Map();
	const flagsMap = new Map();

	ensureArray(board?.stages).forEach((stage) => {
		ensureArray(stage.visits).forEach((visit) => {
			ensureArray(visit.services).forEach((service) => {
				const id = Number(service?.id);
				if (!Number.isFinite(id) || servicesMap.has(id)) {
					return;
				}

				servicesMap.set(id, {
					id,
					name: safeString(service?.name ?? ''),
				});
			});

			ensureArray(visit.flags).forEach((flag) => {
				const id = Number(flag?.id);
				if (!Number.isFinite(id) || flagsMap.has(id)) {
					return;
				}

				flagsMap.set(id, {
					id,
					name: safeString(flag?.name ?? ''),
				});
			});
		});
	});

	const services = Array.from(servicesMap.values()).sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
	const flags = Array.from(flagsMap.values()).sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));

	return { services, flags };
};

const formatCountdownSeconds = (targetTimestamp) => {
	if (!targetTimestamp) {
		return '';
	}

	const delta = Math.round((targetTimestamp - Date.now()) / 1000);
	if (delta <= 0) {
		return '0s';
	}

	if (delta < 60) {
		return `${delta}s`;
	}

	const minutes = Math.floor(delta / 60);
	const seconds = delta % 60;
	return `${minutes}m ${seconds.toString().padStart(2, '0')}s`;
};

const formatCheckIn = (isoString) => {
	const time = formatTime(isoString);
	return time ? `${strings.checkIn} ${time}` : '';
};

const createStore = (initialState = {}) => {
	let state = { ...initialState };
	const listeners = new Set();

	return {
		getState: () => state,
		setState: (updater) => {
			const nextState = typeof updater === 'function' ? updater(state) : updater;
			if (!nextState || typeof nextState !== 'object') {
				return;
			}

			state = { ...state, ...nextState };
			listeners.forEach((listener) => {
				try {
					listener(state);
				} catch (error) {
					// eslint-disable-next-line no-console
					console.error('[BBGF] store listener error', error);
				}
			});
		},
		subscribe: (listener) => {
			if (typeof listener !== 'function') {
				return () => {};
			}

			listeners.add(listener);
			return () => listeners.delete(listener);
		},
	};
};

const createApiClient = (rest = {}, context = {}) => {
	const buildHeaders = () => {
		const headers = {
			Accept: 'application/json',
		};

		if (rest.nonce) {
			headers['X-WP-Nonce'] = rest.nonce;
		}

		return headers;
	};

	const buildUrl = (endpoint, params = {}) => {
		if (!endpoint) {
			return '';
		}

		const url = new URL(endpoint, window.location.origin);
		Object.entries(params).forEach(([key, value]) => {
			if (value === undefined || value === null || value === '') {
				return;
			}

			url.searchParams.append(key, value);
		});

		return url.toString();
	};

	const fetchBoard = async (params = {}) => {
		const query = { ...params };
		if (context.publicToken) {
			query.public_token = context.publicToken;
		}

		const url = buildUrl(rest.endpoints?.board, query);
		if (!url) {
			throw new Error('Board endpoint unavailable');
		}

		const response = await fetch(url, {
			method: 'GET',
			headers: buildHeaders(),
			credentials: 'same-origin',
		});

		if (!response.ok) {
			throw new Error(`Board request failed with status ${response.status}`);
		}

		return response.json();
	};

	const fetchVisit = async (visitId, params = {}) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const query = { ...params };
		if (context.publicToken && !query.public_token) {
			query.public_token = context.publicToken;
		}

		if (context.view && !query.view) {
			query.view = context.view;
		}

		const url = buildUrl(`${endpoint}/${encodeURIComponent(visitId)}`, query);
		const response = await fetch(url, {
			method: 'GET',
			headers: buildHeaders(),
			credentials: 'same-origin',
		});

		if (!response.ok) {
			throw new Error(`Visit request failed with status ${response.status}`);
		}

		return response.json();
	};

	const moveVisit = async (visitId, toStage, payload = {}) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const url = buildUrl(`${endpoint}/${encodeURIComponent(visitId)}/move`);
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				...buildHeaders(),
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				to_stage: toStage,
				comment: payload.comment ?? '',
			}),
		});

		if (!response.ok) {
			let message = `Move failed with status ${response.status}`;
			try {
				const data = await response.json();
				if (data?.message) {
					message = data.message;
				}
			} catch {
				// Ignore parse errors.
			}

			throw new Error(message);
		}

		return response.json();
	};

	const updateVisit = async (visitId, payload = {}) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const url = buildUrl(`${endpoint}/${encodeURIComponent(visitId)}`);
		const response = await fetch(url, {
			method: 'PATCH',
			headers: {
				...buildHeaders(),
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload),
		});

		if (!response.ok) {
			let message = `Update failed with status ${response.status}`;
			try {
				const data = await response.json();
				if (data?.message) {
					message = data.message;
				}
			} catch {
				// Ignore parse errors.
			}

			throw new Error(message);
		}

		return response.json();
	};

	const fetchServices = async () => {
		const endpoint = rest.endpoints?.services;
		if (!endpoint) {
			throw new Error('Services endpoint unavailable');
		}

		const url = buildUrl(endpoint, {
			per_page: 100,
			context: 'view',
		});

		const response = await fetch(url, {
			method: 'GET',
			headers: buildHeaders(),
			credentials: 'same-origin',
		});

		if (!response.ok) {
			throw new Error(`Services request failed with status ${response.status}`);
		}

		return response.json();
	};

	return {
		fetchBoard,
		fetchVisit,
		moveVisit,
		updateVisit,
		fetchServices,
	};
};

const createCardElement = (visit, stage, options) => {
	const { copy, previous, next, canMove, pendingMoves, readonly, draggingVisitId, presentation = {} } = options;
	const showNotes = presentation.showNotes !== false;

	const stageKey = safeString(stage.key ?? stage.stage_key ?? '');
	const card = document.createElement('article');
	card.className = 'bbgf-card';
	card.dataset.visitId = String(visit.id ?? '');
	card.dataset.stage = stageKey;
	card.dataset.updatedAt = safeString(visit.updated_at ?? '');
	card.setAttribute('role', 'listitem');
	card.setAttribute('aria-label', `${safeString(visit.client?.name ?? copy.unknownClient)} — ${safeString(stage.label ?? stageKey)}`);
	if (presentation.mode === 'display') {
		card.setAttribute('tabindex', '-1');
		card.classList.add('bbgf-card--static');
	} else {
		card.setAttribute('tabindex', '0');
		card.classList.remove('bbgf-card--static');
	}

	const visitIdNumber = Number(visit.id);
	const isPending = pendingMoves?.has(visitIdNumber);
	const isDragging = draggingVisitId !== null && visitIdNumber === Number(draggingVisitId);
	const canDrag = !readonly && settings.capabilities?.moveStages;

	if (canDrag) {
		card.setAttribute('draggable', 'true');
		card.classList.add('bbgf-card--draggable');
	} else {
		card.removeAttribute('draggable');
		card.classList.remove('bbgf-card--draggable');
	}

	card.classList.toggle('bbgf-card--pending', Boolean(isPending));
	card.classList.toggle('is-dragging', Boolean(isDragging));
	card.setAttribute('aria-grabbed', isDragging ? 'true' : 'false');

	const timerSeconds = toNumber(visit.timer_elapsed_seconds) ?? 0;
	const timerState = getTimerState(timerSeconds, stage.timer_thresholds ?? {});
	card.classList.add(`bbgf-card--timer-${timerState}`);

	if (timerState === 'critical') {
		card.classList.add('bbgf-card--overdue');
	} else if (timerState === 'warning') {
		card.classList.add('bbgf-card--capacity-warning');
	}

	const photoWrapper = document.createElement('div');
	photoWrapper.className = 'bbgf-card-photo';
	photoWrapper.setAttribute('aria-hidden', 'true');

	const photoData = getVisitPhoto(visit);
	if (photoData && photoData.url) {
		const img = document.createElement('img');
		img.src = photoData.url;
		img.alt = photoData.alt || safeString(visit.client?.name ?? copy.unknownClient);
		photoWrapper.appendChild(img);
	} else {
		const fallback = document.createElement('span');
		fallback.textContent = (safeString(visit.client?.name ?? copy.unknownClient).charAt(0) || '🐾').toUpperCase();
		photoWrapper.appendChild(fallback);
	}

	const body = document.createElement('div');
	body.className = 'bbgf-card-body';

	const header = document.createElement('div');
	header.className = 'bbgf-card-header';

	const name = document.createElement('p');
	name.className = 'bbgf-card-name';
	name.textContent = safeString(visit.client?.name ?? copy.unknownClient);

	const timer = document.createElement('span');
	timer.className = 'bbgf-card-timer';
	timer.dataset.state = timerState;
	timer.dataset.seconds = String(Math.max(0, timerSeconds));
	timer.textContent = formatDuration(timerSeconds);
	name.appendChild(timer);

	header.appendChild(name);

	const meta = document.createElement('p');
	meta.className = 'bbgf-card-meta';

	const serviceSummary = buildServiceSummary(visit.services);
	if (serviceSummary) {
		const serviceSpan = document.createElement('span');
		serviceSpan.textContent = serviceSummary;
		meta.appendChild(serviceSpan);
	}

	const checkInText = formatCheckIn(visit.check_in_at);
	if (checkInText) {
		if (meta.childNodes.length) {
			const separator = document.createElement('span');
			separator.textContent = '•';
			separator.setAttribute('aria-hidden', 'true');
			meta.appendChild(separator);
		}

		const checkInSpan = document.createElement('span');
		checkInSpan.textContent = checkInText;
		meta.appendChild(checkInSpan);
	}

	if (meta.childNodes.length) {
		header.appendChild(meta);
	}

	body.appendChild(header);

	const services = ensureArray(visit.services);
	if (services.length) {
		const servicesWrapper = document.createElement('div');
		servicesWrapper.className = 'bbgf-card-services';
		servicesWrapper.setAttribute('aria-label', copy.services);

		services.forEach((service) => {
			const chip = document.createElement('span');
			chip.className = 'bbgf-service-chip';
			chip.setAttribute('aria-hidden', 'true');

			const icon = safeString(service.icon);
			const label = safeString(service.name);
			chip.textContent = icon && icon !== label ? `${icon} ${label}` : label;

			servicesWrapper.appendChild(chip);
		});

		body.appendChild(servicesWrapper);
	}

	const flags = ensureArray(visit.flags);
	if (flags.length) {
		const flagsWrapper = document.createElement('div');
		flagsWrapper.className = 'bbgf-card-flags';
		flagsWrapper.setAttribute('aria-label', copy.flags);

		flags.forEach((flag) => {
			const chip = document.createElement('span');
			chip.className = 'bbgf-flag-chip';

			const emoji = document.createElement('span');
			emoji.setAttribute('aria-hidden', 'true');
			emoji.textContent = safeString(flag.emoji);
			chip.appendChild(emoji);

			const nameSpan = document.createElement('span');
			nameSpan.textContent = safeString(flag.name);
			chip.appendChild(nameSpan);

			flagsWrapper.appendChild(chip);
		});

		body.appendChild(flagsWrapper);
	}

	const notes = safeString(visit.public_notes ?? visit.instructions ?? '');
	if (showNotes && notes) {
		const notesParagraph = document.createElement('p');
		notesParagraph.className = 'bbgf-card-notes';
		notesParagraph.textContent = notes;
		body.appendChild(notesParagraph);
	}

	if (!readonly && canMove && (previous || next)) {
		const actions = document.createElement('div');
		actions.className = 'bbgf-card-actions';
		actions.setAttribute('aria-label', copy.stageControls);

		if (previous) {
			const prevButton = document.createElement('button');
			prevButton.type = 'button';
			prevButton.className = 'bbgf-button bbgf-move-prev is-disabled';
			prevButton.dataset.targetStage = safeString(previous.key ?? previous.stage_key ?? '');
			prevButton.textContent = copy.movePrev;
			const disabled =
				!previous ||
				!previous.key ||
				readonly ||
				!canMove ||
				(pendingMoves && pendingMoves.has(Number(visit.id)));

			if (disabled) {
				prevButton.disabled = true;
				prevButton.setAttribute('aria-disabled', 'true');
				prevButton.classList.add('is-disabled');
			} else {
				prevButton.disabled = false;
				prevButton.removeAttribute('aria-disabled');
				prevButton.classList.remove('is-disabled');
			}
			actions.appendChild(prevButton);
		}

		if (next) {
			const nextButton = document.createElement('button');
			nextButton.type = 'button';
			nextButton.className = 'bbgf-button bbgf-move-next is-disabled';
			nextButton.dataset.targetStage = safeString(next.key ?? next.stage_key ?? '');
			nextButton.textContent = copy.moveNext;
			const disabled =
				!next ||
				!next.key ||
				readonly ||
				!canMove ||
				(pendingMoves && pendingMoves.has(Number(visit.id)));

			if (disabled) {
				nextButton.disabled = true;
				nextButton.setAttribute('aria-disabled', 'true');
				nextButton.classList.add('is-disabled');
			} else {
				nextButton.disabled = false;
				nextButton.removeAttribute('aria-disabled');
				nextButton.classList.remove('is-disabled');
			}
			actions.appendChild(nextButton);
		}

		if (actions.childNodes.length) {
			body.appendChild(actions);
		}
	}

	card.appendChild(photoWrapper);
	card.appendChild(body);

	return card;
};

const createColumnElement = (stage, options) => {
	const { copy, previous, next, canMove, pendingMoves, readonly, draggingVisitId, searchActive, presentation = {} } = options;

	const stageKey = safeString(stage.key ?? stage.stage_key ?? '');
	const label = safeString(stage.label ?? stageKey);
	const capacity = stage.capacity ?? {};
	const visits = ensureArray(stage.visits);
	const count = visits.length;
	const softLimit = toNumber(capacity.soft);
	const hardLimit = toNumber(capacity.hard);

	const column = document.createElement('section');
	column.className = 'bbgf-column';
	column.dataset.stage = stageKey;
	column.dataset.capacitySoft = Number.isFinite(softLimit) ? String(softLimit) : '';
	column.dataset.capacityHard = Number.isFinite(hardLimit) ? String(hardLimit) : '';
	column.dataset.visitCount = String(count);
	column.dataset.availableSoft = Number.isFinite(capacity.available_soft) ? String(capacity.available_soft) : '';
	column.dataset.availableHard = Number.isFinite(capacity.available_hard) ? String(capacity.available_hard) : '';
	column.dataset.softExceeded = capacity.is_soft_exceeded ? 'true' : 'false';
	column.dataset.hardExceeded = capacity.is_hard_exceeded ? 'true' : 'false';
	column.dataset.capacityHint = buildCapacityHint(capacity, count);
	column.setAttribute('role', 'listitem');
	column.setAttribute('aria-label', label);
	column.setAttribute('aria-dropeffect', readonly ? 'none' : 'move');

	if (capacity.is_soft_exceeded && !capacity.is_hard_exceeded) {
		column.classList.add('bbgf-column--soft-full');
	}

	if (capacity.is_hard_exceeded) {
		column.classList.add('bbgf-column--hard-full');
	}

	const nearCapacity =
		!capacity.is_soft_exceeded &&
		!capacity.is_hard_exceeded &&
		Number.isFinite(softLimit) &&
		softLimit > 0 &&
		(Number.isFinite(capacity.available_soft) ? capacity.available_soft : softLimit - count) <= 1;

	if (nearCapacity) {
		column.classList.add('bbgf-column--near-capacity');
	}

	const header = document.createElement('header');
	header.className = 'bbgf-column-header';

	const title = document.createElement('div');
	title.className = 'bbgf-column-title';

	const labelSpan = document.createElement('span');
	labelSpan.className = 'bbgf-column-label';
	labelSpan.textContent = label;
	title.appendChild(labelSpan);

	const countSpan = document.createElement('span');
	countSpan.className = 'bbgf-column-count';
	countSpan.dataset.role = 'bbgf-column-count';
	countSpan.dataset.softLimit = Number.isFinite(softLimit) ? String(softLimit) : '';
	countSpan.textContent = Number.isFinite(softLimit) && softLimit > 0 ? `${count} / ${softLimit}` : String(count);
	countSpan.setAttribute('aria-label', Number.isFinite(softLimit) && softLimit > 0 ? `${count} of ${softLimit}` : String(count));
	title.appendChild(countSpan);

	header.appendChild(title);

	const capacityBadge = document.createElement('span');
	capacityBadge.className = 'bbgf-capacity-badge';
	capacityBadge.dataset.role = 'bbgf-capacity-hint';
	capacityBadge.setAttribute('aria-hidden', 'true');
	capacityBadge.textContent = column.dataset.capacityHint;
	header.appendChild(capacityBadge);

	column.appendChild(header);

	const body = document.createElement('div');
	body.className = 'bbgf-column-body';
	body.setAttribute('role', 'list');
	body.setAttribute('aria-label', `${label} column cards`);

	if (!visits.length) {
		const empty = document.createElement('p');
		empty.className = 'bbgf-column-empty';
		empty.textContent = searchActive ? copy.noVisits : copy.emptyColumn;
		body.appendChild(empty);
	} else {
		visits.forEach((visit) => {
			const card = createCardElement(visit, stage, { copy, previous, next, canMove, pendingMoves, readonly, draggingVisitId, presentation });
			body.appendChild(card);
		});
	}

	column.appendChild(body);

	return column;
};

const getActiveViewSlug = (state) =>
	state.viewOverride ||
	state.board?.view?.slug ||
	settings.context?.view ||
	settings.view?.slug ||
	'';

const buildDisplayControls = (ui) => {
	if (!ui.showFullscreen) {
		return null;
	}

	const controls = document.createElement('div');
	controls.className = 'bbgf-display-controls';

	const fullscreenToggle = document.createElement('button');
	fullscreenToggle.type = 'button';
	fullscreenToggle.className = 'bbgf-button bbgf-button--ghost bbgf-toolbar-fullscreen';
	fullscreenToggle.dataset.role = 'bbgf-fullscreen-toggle';
	fullscreenToggle.textContent = strings.fullscreen;

	controls.appendChild(fullscreenToggle);

	return controls;
};

const buildInteractiveToolbar = (state, board, config, copy, ui) => {
	if (!ui.showToolbar) {
		return null;
	}

	const toolbar = document.createElement('div');
	toolbar.id = 'bbgf-board-toolbar';
	toolbar.className = 'bbgf-board-toolbar';

	const views = ensureArray(config.views);
	const activeSlug = getActiveViewSlug(state);

	if (ui.viewSwitcher !== 'none' && views.length) {
		if (ui.viewSwitcher === 'dropdown') {
			const viewWrapper = document.createElement('div');
			viewWrapper.className = 'bbgf-toolbar-view';
			viewWrapper.setAttribute('role', 'group');
			viewWrapper.setAttribute('aria-label', copy.viewSwitcher);

			const select = document.createElement('select');
			select.className = 'bbgf-view-select';
			select.dataset.role = 'bbgf-view-select';

			views.forEach((view) => {
				const option = document.createElement('option');
				const slug = safeString(view.slug ?? view.key ?? '');
				option.value = slug;
				option.textContent = safeString(view.name ?? view.label ?? slug);
				if (slug === activeSlug) {
					option.selected = true;
				}
				select.appendChild(option);
			});

			viewWrapper.appendChild(select);
			toolbar.appendChild(viewWrapper);
		} else {
			const viewGroup = document.createElement('div');
			viewGroup.className = 'bbgf-toolbar-view';
			viewGroup.setAttribute('role', 'group');
			viewGroup.setAttribute('aria-label', copy.viewSwitcher);

			views.forEach((view) => {
				const slug = safeString(view.slug ?? view.key ?? '');
				if (!slug) {
					return;
				}

				const button = document.createElement('button');
				button.type = 'button';
				button.className = `bbgf-button ${slug === activeSlug ? 'bbgf-button--primary' : 'bbgf-button--ghost'}`;
				button.dataset.view = slug;
				button.setAttribute('aria-pressed', slug === activeSlug ? 'true' : 'false');
				button.textContent = safeString(view.name ?? view.label ?? slug);

				viewGroup.appendChild(button);
			});

			if (viewGroup.childNodes.length) {
				toolbar.appendChild(viewGroup);
			}
		}
	}

	if (ui.showSearch) {
		const searchWrapper = document.createElement('div');
		searchWrapper.className = 'bbgf-toolbar-search';
		const searchLabel = document.createElement('label');
		searchLabel.className = 'screen-reader-text';
		searchLabel.textContent = copy.searchPlaceholder;
		const searchInput = document.createElement('input');
		searchInput.type = 'search';
		searchInput.className = 'bbgf-toolbar-search__input';
		searchInput.placeholder = copy.searchPlaceholder;
		searchInput.value = state.filters?.query ?? '';
		searchInput.dataset.role = 'bbgf-toolbar-search';
		searchWrapper.appendChild(searchLabel);
		searchWrapper.appendChild(searchInput);
		toolbar.appendChild(searchWrapper);
	}

	let filterWrapper = null;
	if (ui.showFilters) {
		const filterOptions = getBoardFilterOptions(board);
		const filtersState = state.filters ?? { query: '', services: [], flags: [] };

		filterWrapper = document.createElement('div');
		filterWrapper.className = 'bbgf-toolbar-filters';

		const makeFilter = (labelText, role, options, selectedValues) => {
			if (!options.length) {
				return null;
			}

			const field = document.createElement('label');
			field.className = 'bbgf-toolbar-filter';
			field.innerHTML = `<span>${escapeHtml(labelText)}</span>`;

			const select = document.createElement('select');
			select.className = 'bbgf-toolbar-select';
			select.multiple = true;
			select.size = Math.min(options.length, 6);
			select.dataset.role = role;

			const selectedSet = new Set(ensureArray(selectedValues).map((value) => String(value)));

			options.forEach((option) => {
				const opt = document.createElement('option');
				opt.value = String(option.id);
				opt.textContent = option.name;
				if (selectedSet.has(String(option.id))) {
					opt.selected = true;
				}
				select.appendChild(opt);
			});

			field.appendChild(select);

			return field;
		};

		const servicesFilter = makeFilter(copy.filterServices, 'bbgf-filter-services', filterOptions.services, filtersState.services || []);
		const flagsFilter = makeFilter(copy.filterFlags, 'bbgf-filter-flags', filterOptions.flags, filtersState.flags || []);

		if (servicesFilter) {
			filterWrapper.appendChild(servicesFilter);
		}

		if (flagsFilter) {
			filterWrapper.appendChild(flagsFilter);
		}

		if (filterWrapper.childNodes.length) {
			const resetButton = document.createElement('button');
			resetButton.type = 'button';
			resetButton.className = 'bbgf-button bbgf-toolbar-reset';
			resetButton.dataset.role = 'bbgf-filter-reset';
			resetButton.textContent = copy.filterAll;
			filterWrapper.appendChild(resetButton);
		}
	}

	if (filterWrapper && filterWrapper.childNodes.length) {
		toolbar.appendChild(filterWrapper);
	}

	const controls = document.createElement('div');
	controls.className = 'bbgf-toolbar-controls';

	if (ui.showRefresh) {
		const refreshButton = document.createElement('button');
		refreshButton.type = 'button';
		refreshButton.className = 'bbgf-refresh-button bbgf-button';
		refreshButton.textContent = copy.refresh;
		if (state.isLoading) {
			refreshButton.setAttribute('disabled', 'disabled');
			refreshButton.classList.add('is-disabled');
		}
		controls.appendChild(refreshButton);
	}

	if (ui.showLastUpdated) {
		const lastUpdated = document.createElement('span');
		lastUpdated.className = 'bbgf-last-updated';
		lastUpdated.dataset.role = 'bbgf-last-updated';
		lastUpdated.textContent = board.last_updated ? `${copy.lastUpdated} ${formatDateForLastUpdated(board.last_updated)}` : copy.lastUpdated;
		controls.appendChild(lastUpdated);
	}

	if (ui.showCountdown) {
		const refreshCountdown = document.createElement('span');
		refreshCountdown.className = 'bbgf-next-refresh';
		refreshCountdown.dataset.role = 'bbgf-next-refresh';
		const countdownText = formatCountdownSeconds(state.nextRefreshAt);
		refreshCountdown.textContent = countdownText ? `${copy.autoRefresh} ${countdownText}` : `${copy.autoRefresh} …`;
		controls.appendChild(refreshCountdown);
	}

	if (ui.showFullscreen) {
		const fullscreenToggle = document.createElement('button');
		fullscreenToggle.type = 'button';
		fullscreenToggle.className = 'bbgf-button bbgf-toolbar-fullscreen';
		fullscreenToggle.dataset.role = 'bbgf-fullscreen-toggle';
		fullscreenToggle.textContent = strings.fullscreen;
		controls.appendChild(fullscreenToggle);
	}

	if (controls.childNodes.length) {
		toolbar.appendChild(controls);
	}

	if (ui.showMaskBadge && board.visibility?.mask_guardian) {
		const maskBadge = document.createElement('span');
		maskBadge.className = 'bbgf-toolbar-badge';
		maskBadge.textContent = copy.maskedGuardian;
		toolbar.appendChild(maskBadge);
	}

	if (!toolbar.childNodes.length) {
		return null;
	}

	return toolbar;
};

const renderBoard = (state, root, config, copy) => {
	root.classList.add('bbgf-board--bootstrapped');

	const board = state.board;
	if (!board) {
		root.innerHTML = '';
		const loading = document.createElement('div');
		loading.className = 'bbgf-board-loading';
		loading.textContent = copy.loading;
		root.appendChild(loading);
		return;
	}

	const ui = resolvePresentationConfig(board);
	currentPresentation = ui;

	root.dataset.readonly = board.readonly ? 'true' : 'false';
	root.dataset.isPublic = board.is_public ? 'true' : 'false';
	root.dataset.activeView = getActiveViewSlug(state);
	const viewType = safeString(board.view?.type ?? '');
	root.dataset.viewType = viewType;
	root.dataset.boardMode = ui.mode;

	if (board.readonly) {
		root.classList.add('bbgf-board--readonly');
	} else {
		root.classList.remove('bbgf-board--readonly');
	}

	if (ui.mode === 'display') {
		root.classList.add('bbgf-board-wrapper--display');
	} else {
		root.classList.remove('bbgf-board-wrapper--display');
	}

	root.innerHTML = '';
	renderErrors(state, root, copy);

	if (ui.mode === 'display') {
		const controls = buildDisplayControls(ui);
		if (controls) {
			root.appendChild(controls);
		}
	} else {
		const toolbar = buildInteractiveToolbar(state, board, config, copy, ui);
		if (toolbar) {
			root.appendChild(toolbar);
		}
	}

	const columnsWrapper = document.createElement('div');
	columnsWrapper.className = 'bbgf-board';
	columnsWrapper.setAttribute('role', 'list');

	const searchQuery = (state.filters?.query ?? '').trim().toLowerCase();
	const selectedServices = ensureArray(state.filters?.services).map((value) => Number(value)).filter((value) => Number.isFinite(value));
	const selectedFlags = ensureArray(state.filters?.flags).map((value) => Number(value)).filter((value) => Number.isFinite(value));
	const searchActive = Boolean(searchQuery);
	const allowFilters = ui.showSearch || ui.showFilters;
	const filtersActive = allowFilters && (searchActive || selectedServices.length > 0 || selectedFlags.length > 0);
	const baseStages = ensureArray(board.stages);

	const filterVisit = (visit) => {
		if (!visit) {
			return false;
		}

		if (searchActive && !visitMatchesQuery(visit, searchQuery)) {
			return false;
		}

		if (selectedServices.length) {
			const visitServiceIds = ensureArray(visit.services)
				.map((service) => Number(service?.id))
				.filter((value) => Number.isFinite(value));
			const serviceSet = new Set(visitServiceIds);
			const matchesServices = selectedServices.every((id) => serviceSet.has(id));
			if (!matchesServices) {
				return false;
			}
		}

		if (selectedFlags.length) {
			const visitFlagIds = ensureArray(visit.flags)
				.map((flag) => Number(flag?.id))
				.filter((value) => Number.isFinite(value));
			const flagSet = new Set(visitFlagIds);
			const matchesFlags = selectedFlags.every((id) => flagSet.has(id));
			if (!matchesFlags) {
				return false;
			}
		}

		return true;
	};

	const stages = baseStages.map((stage) => {
		const visits = ensureArray(stage.visits);
		if (!filtersActive) {
			return { ...stage };
		}

		const filteredVisits = visits.filter(filterVisit);
		return {
			...stage,
			visits: filteredVisits,
		};
	});

	root.dataset.searchActive = filtersActive ? 'true' : 'false';
	if (!stages.length) {
		const empty = document.createElement('p');
		empty.className = 'bbgf-board-empty';
		empty.textContent = copy.noVisits;
		columnsWrapper.appendChild(empty);
	} else {
		const pendingSet = new Set(ensureArray(state.pendingMoves).map((id) => Number(id)));
		const draggingVisitId = state.drag?.isDragging ? state.drag.visitId : null;

		stages.forEach((stage, index) => {
			const previous = stages[index - 1] || null;
			const next = stages[index + 1] || null;
			const column = createColumnElement(stage, {
				copy,
				previous,
				next,
				canMove: Boolean(config.capabilities?.moveStages) && !board.readonly,
				pendingMoves: pendingSet,
				readonly: board.readonly,
				draggingVisitId,
				searchActive: filtersActive,
				presentation: ui,
			});
			columnsWrapper.appendChild(column);
		});
	}

	root.appendChild(columnsWrapper);

	const existingModal = root.querySelector('#bbgf-modal');
	if (!existingModal) {
		const modal = document.createElement('div');
		modal.id = 'bbgf-modal';
		modal.setAttribute('hidden', 'hidden');
		modal.className = 'bbgf-modal';
		modal.innerHTML = `
			<div class="bbgf-modal__backdrop" data-role="bbgf-modal-backdrop"></div>
			<div class="bbgf-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbgf-modal-title">
				<header class="bbgf-modal__header">
					<h2 id="bbgf-modal-title" class="bbgf-modal__title">${copy.modalTitle}</h2>
					<span class="bbgf-modal__badge" data-role="bbgf-modal-readonly" hidden>${copy.modalReadOnly}</span>
					<button type="button" class="bbgf-button bbgf-modal-close" data-role="bbgf-modal-close">${copy.modalClose}</button>
				</header>
				<nav class="bbgf-modal__tabs" data-role="bbgf-modal-tabs"></nav>
				<div class="bbgf-modal__body" data-role="bbgf-modal-body">
					<p class="bbgf-modal__loading">${copy.modalLoading}</p>
				</div>
			</div>
		`.trim();
		root.appendChild(modal);
	}

	const lastUpdatedNode = root.querySelector('[data-role="bbgf-last-updated"]');
	if (lastUpdatedNode) {
		lastUpdatedNode.dataset.timestamp = safeString(board.last_updated ?? state.lastFetchedAt ?? '');
	}
};

const handleToolbarSearchInput = (event) => {
    const value = event.target.value ?? '';
    setFilters({ query: value });
};

const handleFilterChange = (event) => {
    const role = event.target.dataset.role;
    if (!role) {
        return;
    }

    const selectedValues = Array.from(event.target.selectedOptions || [])
        .map((option) => Number(option.value))
        .filter((value) => Number.isFinite(value));

    if (role === 'bbgf-filter-services') {
        setFilters({ services: selectedValues });
    } else if (role === 'bbgf-filter-flags') {
        setFilters({ flags: selectedValues });
    }
};

const handleFilterReset = () => {
    setFilters({ query: '', services: [], flags: [] });
};

const dismissError = () => {
	store.setState((state) => ({
		...state,
		errors: ensureArray(state.errors).slice(0, -1),
	}));

	if (toastTimer) {
		window.clearTimeout(toastTimer);
		toastTimer = null;
	}

	const container = boardRootElement?.querySelector('#bbgf-board-errors');
	if (container) {
		container.classList.remove('is-active');
	}
};

const renderErrors = (state, root, copy) => {
	const existing = root.querySelector('#bbgf-board-errors');
	const message = ensureArray(state.errors).slice(-1)[0];

	if (!message) {
		if (existing) {
			existing.remove();
		}
		if (toastTimer) {
			window.clearTimeout(toastTimer);
			toastTimer = null;
		}
		return;
	}

	let container = existing;
	if (!container) {
		container = document.createElement('div');
		container.id = 'bbgf-board-errors';
		container.className = 'bbgf-toast';
		container.innerHTML = `
			<p class="bbgf-toast__message"></p>
			<button type="button" class="bbgf-button bbgf-button--ghost bbgf-toast__dismiss" data-role="bbgf-toast-dismiss" aria-label="${escapeHtml(
				copy.modalClose
			)}">×</button>
		`;
		container.querySelector('[data-role="bbgf-toast-dismiss"]').addEventListener('click', dismissError);
		root.appendChild(container);
	}

	const messageNode = container.querySelector('.bbgf-toast__message');
	if (messageNode) {
		messageNode.textContent = message;
	}

	container.classList.add('is-active');

	if (toastTimer) {
		window.clearTimeout(toastTimer);
	}

	toastTimer = window.setTimeout(() => {
		container?.classList.remove('is-active');
		dismissError();
	}, 6000);
};

const attachToolbarHandlers = (root) => {
	const refreshButton = root.querySelector('.bbgf-refresh-button');
	if (refreshButton && !refreshButton.dataset.bound) {
		refreshButton.addEventListener('click', () => {
			if (refreshButton.disabled) {
				return;
			}

			loadBoard({ reason: 'manual', forceFull: true });
		});
		refreshButton.dataset.bound = 'true';
	}

	const viewButtons = root.querySelectorAll('.bbgf-toolbar-view button[data-view]');
	if (viewButtons.length) {
		viewButtons.forEach((button) => {
			if (button.dataset.bound === 'true') {
				return;
			}

			button.addEventListener('click', () => {
				const targetView = button.dataset.view;
				if (!targetView || targetView === getActiveViewSlug(store.getState())) {
					return;
				}

				store.setState({
					viewOverride: targetView,
				});

				loadBoard({ reason: 'view-change', forceFull: true, targetView });
			});
			button.dataset.bound = 'true';
		});
	}

	const viewSelect = root.querySelector('[data-role="bbgf-view-select"]');
	if (viewSelect && viewSelect.dataset.bound !== 'true') {
		viewSelect.addEventListener('change', () => {
			const targetView = viewSelect.value;
			if (!targetView || targetView === getActiveViewSlug(store.getState())) {
				return;
			}

			store.setState({
				viewOverride: targetView,
			});

			loadBoard({ reason: 'view-change', forceFull: true, targetView });
		});
		viewSelect.dataset.bound = 'true';
	}

	const searchInput = root.querySelector('[data-role="bbgf-toolbar-search"]');
	if (searchInput && searchInput.dataset.bound !== 'true') {
		searchInput.addEventListener('input', handleToolbarSearchInput);
		searchInput.dataset.bound = 'true';
	}

	const filterSelects = root.querySelectorAll('[data-role="bbgf-filter-services"], [data-role="bbgf-filter-flags"]');
	filterSelects.forEach((select) => {
		if (select.dataset.bound === 'true') {
			return;
		}
		select.addEventListener('change', handleFilterChange);
		select.dataset.bound = 'true';
	});

	const resetButton = root.querySelector('[data-role="bbgf-filter-reset"]');
	if (resetButton && resetButton.dataset.bound !== 'true') {
		resetButton.addEventListener('click', handleFilterReset);
		resetButton.dataset.bound = 'true';
	}

	fullscreenButton = root.querySelector('[data-role="bbgf-fullscreen-toggle"]') || fullscreenButton;
	if (fullscreenButton && fullscreenButton.dataset.bound !== 'true') {
		fullscreenButton.addEventListener('click', toggleFullscreen);
		fullscreenButton.dataset.bound = 'true';
		updateFullscreenButton();
	}
};

const updateToolbarStates = (root, state) => {
	const refreshButton = root.querySelector('.bbgf-refresh-button');
	if (refreshButton) {
		if (state.isLoading) {
			refreshButton.setAttribute('disabled', 'disabled');
			refreshButton.classList.add('is-disabled');
		} else {
			refreshButton.removeAttribute('disabled');
			refreshButton.classList.remove('is-disabled');
		}
	}

	const viewButtons = root.querySelectorAll('.bbgf-toolbar-view button[data-view]');
	if (viewButtons.length) {
		const active = getActiveViewSlug(state);
		viewButtons.forEach((button) => {
			const isActive = button.dataset.view === active;
			button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
			if (isActive) {
				button.classList.add('bbgf-button--primary');
				button.classList.remove('bbgf-button--ghost');
			} else {
				button.classList.remove('bbgf-button--primary');
				button.classList.add('bbgf-button--ghost');
			}
		});
	}

	const viewSelect = root.querySelector('[data-role="bbgf-view-select"]');
	if (viewSelect) {
		const active = getActiveViewSlug(state);
		if (viewSelect.value !== active) {
			viewSelect.value = active;
		}
	}

	const searchInput = root.querySelector('[data-role="bbgf-toolbar-search"]');
	if (searchInput && document.activeElement !== searchInput) {
		searchInput.value = state.filters?.query ?? '';
	}

	const serviceSelect = root.querySelector('[data-role="bbgf-filter-services"]');
	if (serviceSelect) {
		const selected = new Set((state.filters?.services || []).map((value) => String(value)));
		Array.from(serviceSelect.options).forEach((option) => {
			option.selected = selected.has(option.value);
		});
	}

	const flagSelect = root.querySelector('[data-role="bbgf-filter-flags"]');
	if (flagSelect) {
		const selected = new Set((state.filters?.flags || []).map((value) => String(value)));
		Array.from(flagSelect.options).forEach((option) => {
			option.selected = selected.has(option.value);
		});
	}

	const countdownNode = root.querySelector('[data-role="bbgf-next-refresh"]');
	if (countdownNode) {
		const countdown = formatCountdownSeconds(state.nextRefreshAt);
		countdownNode.textContent = countdown ? `${strings.autoRefresh} ${countdown}` : `${strings.autoRefresh} …`;
	}

	fullscreenButton = root.querySelector('[data-role="bbgf-fullscreen-toggle"]') || fullscreenButton;
	updateFullscreenButton();
};

const setupLastUpdatedTicker = (root) => {
	const target = root.querySelector('[data-role="bbgf-last-updated"]');
	if (!target) {
		if (root._bbgfLastUpdatedInterval) {
			window.clearInterval(root._bbgfLastUpdatedInterval);
			root._bbgfLastUpdatedInterval = null;
		}
		return;
	}

	const update = () => {
		const base = target.dataset.timestamp;
		const humanized = humanizeRelativeTime(base);
		target.textContent = base ? `${strings.lastUpdated} ${humanized || formatDateForLastUpdated(base)}` : strings.lastUpdated;

		const refreshNode = root.querySelector('[data-role="bbgf-next-refresh"]');
		if (refreshNode) {
			const countdown = formatCountdownSeconds(store.getState().nextRefreshAt);
			refreshNode.textContent = countdown ? `${strings.autoRefresh} ${countdown}` : `${strings.autoRefresh} …`;
		}
	};

	if (root._bbgfLastUpdatedInterval) {
		window.clearInterval(root._bbgfLastUpdatedInterval);
	}

	update();
	root._bbgfLastUpdatedInterval = window.setInterval(update, 15000);
};

const announce = (message) => {
	if (message && window.wp?.a11y?.speak) {
		window.wp.a11y.speak(message, 'polite');
	}
};

const initialState = {
	board: settings.initialBoard || null,
	isLoading: false,
	errors: [],
	lastFetchedAt: null,
	viewOverride: '',
	pendingMoves: [],
	filters: {
		query: '',
		services: [],
		flags: [],
	},
	nextRefreshAt: null,
	drag: {
		isDragging: false,
		visitId: null,
		fromStage: '',
	},
	modal: {
		isOpen: false,
		visitId: null,
		loading: false,
		visit: null,
		readOnly: false,
		activeTab: 'summary',
		isSaving: false,
		form: {
			instructions: '',
			publicNotes: '',
			privateNotes: '',
			services: [],
		},
		error: '',
	},
	catalog: {
		services: {
			items: [],
			isLoading: false,
			loaded: false,
			error: '',
		},
	},
};

const store = createStore(initialState);
const api = createApiClient(settings.rest || {}, settings.context || {});

const setModalState = (updater) => {
	store.setState((state) => {
		const modal = typeof updater === 'function' ? updater(state.modal, state) : { ...state.modal, ...updater };
		return {
			...state,
			modal: modal,
		};
	});
};

const setDragState = (updater) => {
	store.setState((state) => {
		const drag = typeof updater === 'function' ? updater(state.drag, state) : { ...state.drag, ...updater };
		return {
			...state,
			drag,
		};
	});
};

const setFilters = (partial) => {
	store.setState((state) => ({
		...state,
		filters: {
			...state.filters,
			...partial,
		},
	}));
};

const setNextRefreshAt = (timestamp) => {
	store.setState((state) => ({
		...state,
		nextRefreshAt: timestamp,
	}));
};

const ensureServicesCatalog = async () => {
	if (!settings.capabilities?.manageServices) {
		return;
	}

	const state = store.getState();
	if (state.catalog.services.loaded || state.catalog.services.isLoading) {
		return;
	}

	store.setState((current) => ({
		catalog: {
			...current.catalog,
			services: {
				...current.catalog.services,
				isLoading: true,
				error: '',
			},
		},
	}));

	try {
		const services = await api.fetchServices();
		store.setState((current) => ({
			catalog: {
				...current.catalog,
				services: {
					items: services,
					isLoading: false,
					loaded: true,
					error: '',
				},
			},
		}));
	} catch (error) {
		store.setState((current) => ({
			catalog: {
				...current.catalog,
				services: {
					items: ensureArray(current.catalog.services.items),
					isLoading: false,
					loaded: false,
					error: error?.message || strings.loadingError,
				},
			},
		}));
	}
};

const updateModalFormField = (field, value) => {
	setModalState((modal) => ({
		...modal,
		form: {
			...modal.form,
			[field]: value,
		},
	}));
};

const toggleModalService = (serviceId, isChecked) => {
	setModalState((modal) => {
		const current = new Set(ensureArray(modal.form.services).map((id) => Number(id)));
		if (isChecked) {
			current.add(serviceId);
		} else {
			current.delete(serviceId);
		}

		return {
			...modal,
			form: {
				...modal.form,
				services: Array.from(current.values()),
			},
		};
	});
};

const handleModalTabChange = (tabId) => {
	setModalState((modal) => ({
		...modal,
		activeTab: tabId,
		error: '',
	}));

	if (tabId === 'services') {
		ensureServicesCatalog();
	}
};

const refreshBoardAfterModalSave = () => {
	loadBoard({ reason: 'modal-save', forceFull: true });
};

const saveModalNotes = async () => {
	const state = store.getState();
	const { modal } = state;
	if (modal.readOnly || modal.isSaving) {
		return;
	}

	setModalState({ isSaving: true, error: '' });

	try {
		const payload = {
			instructions: modal.form.instructions,
			public_notes: modal.form.publicNotes,
			private_notes: modal.form.privateNotes,
		};

		const updated = await api.updateVisit(modal.visitId, payload);

		setModalState((current) => ({
			...current,
			isSaving: false,
			visit: updated,
			form: buildModalFormFromVisit(updated),
			error: '',
		}));

		announce(strings.modalSave);
		refreshBoardAfterModalSave();
	} catch (error) {
		setModalState((current) => ({
			...current,
			isSaving: false,
			error: error?.message || strings.loadingError,
		}));
	}
};

const saveModalServices = async () => {
	const state = store.getState();
	const { modal } = state;
	if (modal.readOnly || modal.isSaving) {
		return;
	}

	setModalState({ isSaving: true, error: '' });

	try {
		const payload = {
			services: ensureArray(modal.form.services).map((id) => Number(id)).filter((id) => Number.isFinite(id)),
		};

		const updated = await api.updateVisit(modal.visitId, payload);

		setModalState((current) => ({
			...current,
			isSaving: false,
			visit: updated,
			form: {
				...current.form,
				services: ensureArray(updated.services)
					.map((service) => Number(service?.id))
					.filter((id) => Number.isFinite(id)),
			},
			error: '',
		}));

		announce(strings.modalSave);
		refreshBoardAfterModalSave();
	} catch (error) {
		setModalState((current) => ({
			...current,
			isSaving: false,
			error: error?.message || strings.loadingError,
		}));
	}
};

const handleModalSave = (section) => {
	switch (section) {
		case 'notes':
			saveModalNotes();
			break;
		case 'services':
			saveModalServices();
			break;
		default:
			break;
	}
};

const getPollIntervalMs = () => {
	const state = store.getState();
	const viewInterval = toNumber(state.board?.view?.refresh_interval);
	const configInterval = toNumber(settings.view?.refresh_interval);
	const defaultInterval = toNumber(settings.pollInterval) ?? 30;
	const seconds = viewInterval || configInterval || defaultInterval || 30;

	return Math.max(5, seconds) * 1000;
};

let pollTimer = null;
let modalLastFocusedElement = null;
let boardRootElement = null;
let currentDragHoverStage = '';
let toastTimer = null;
let fullscreenButton = null;
let currentPresentation = { mode: 'interactive' };

const updateFullscreenButton = () => {
	if (!fullscreenButton) {
		return;
	}

	const isFullscreen = Boolean(document.fullscreenElement);
	fullscreenButton.textContent = isFullscreen ? strings.exitFullscreen : strings.fullscreen;
	fullscreenButton.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
};

const toggleFullscreen = () => {
	const root = boardRootElement;
	if (!root) {
		return;
	}

	if (!document.fullscreenElement) {
		root.requestFullscreen?.();
	} else {
		document.exitFullscreen?.();
	}

	updateFullscreenButton();
};

const clearPollTimer = () => {
	if (pollTimer) {
		window.clearTimeout(pollTimer);
		pollTimer = null;
	}
};

const scheduleNextPoll = () => {
	clearPollTimer();

	const delay = getPollIntervalMs();
	setNextRefreshAt(Date.now() + delay);
	pollTimer = window.setTimeout(() => {
		loadBoard({ reason: 'poll' });
	}, delay);
};

const loadBoard = async ({ reason = 'manual', forceFull = false, targetView } = {}) => {
	clearPollTimer();
	setNextRefreshAt(null);

	const currentState = store.getState();
	const activeView = targetView || getActiveViewSlug(currentState);
	const params = {};

	if (activeView) {
		params.view = activeView;
	}

	const useModifiedAfter = !forceFull && !!currentState.board?.last_updated;
	if (useModifiedAfter) {
		params.modified_after = currentState.board.last_updated;
	}

	store.setState({
		isLoading: true,
	});

	try {
		const response = await api.fetchBoard(params);

		store.setState((state) => {
			const mergedBoard = useModifiedAfter && state.board ? applyBoardPatch(state.board, response) : response;

			return {
				board: mergedBoard,
				isLoading: false,
				errors: [],
				lastFetchedAt: new Date().toISOString(),
			};
		});
	} catch (error) {
		const message = error?.message || strings.errorFetching || strings.loadingError;

		store.setState((state) => ({
			isLoading: false,
			errors: [...ensureArray(state.errors), message],
		}));

		announce(strings.errorFetching || strings.loadingError);
	}

	scheduleNextPoll();
};

const updatePendingMoves = (visitId, action = 'add') => {
	const visitNumericId = Number(visitId);
	if (Number.isNaN(visitNumericId)) {
		return;
	}

	store.setState((state) => {
		const existing = new Set(ensureArray(state.pendingMoves).map((id) => Number(id)));

		if (action === 'remove') {
			existing.delete(visitNumericId);
		} else {
			existing.add(visitNumericId);
		}

		return {
			pendingMoves: Array.from(existing.values()),
		};
	});
};

const moveVisitToStage = async (visitId, toStage) => {
	if (!toStage) {
		return;
	}

	const state = store.getState();
	if (state.board?.readonly || !settings.capabilities?.moveStages) {
		return;
	}

	if (state.pendingMoves?.includes(Number(visitId))) {
		return;
	}

	updatePendingMoves(visitId, 'add');

	try {
		await api.moveVisit(visitId, toStage);
		announce(strings.moveSuccess);
		await loadBoard({ reason: 'move', forceFull: true });
	} catch (error) {
		const message = error?.message || strings.errorFetching || strings.loadingError;
		store.setState((currentState) => ({
			errors: [...ensureArray(currentState.errors), message],
		}));
		announce(message);
	} finally {
		updatePendingMoves(visitId, 'remove');
	}
};

const moveVisitWithDirection = (card, direction) => {
	const visitId = Number(card.dataset.visitId);
	const fromStage = safeString(card.dataset.stage);
	if (Number.isNaN(visitId) || !fromStage) {
		return;
	}

	const board = store.getState().board;
	const { prev, next } = getStageNeighbors(board, fromStage);
	const target = direction === 'prev' ? prev?.key : next?.key;
	if (!target || target === fromStage) {
		return;
	}

	moveVisitToStage(visitId, target);
};

const handleQuickMove = async (button) => {
	const card = button.closest('.bbgf-card');
	if (!card) {
		return;
	}

	const visitId = Number(card.dataset.visitId);
	const toStage = safeString(button.dataset.targetStage);

	if (Number.isNaN(visitId) || !toStage) {
		return;
	}

	const state = store.getState();
	if (state.board?.readonly || !settings.capabilities?.moveStages) {
		return;
	}

	button.disabled = true;
	button.classList.add('is-disabled');

	await moveVisitToStage(visitId, toStage);

	button.disabled = false;
	button.classList.remove('is-disabled');
};

const handleBoardClick = (event) => {
	if (currentPresentation.mode === 'display') {
		return;
	}

	const moveNext = event.target.closest('.bbgf-move-next');
	if (moveNext) {
		event.preventDefault();
		handleQuickMove(moveNext);
		return;
	}

	const movePrev = event.target.closest('.bbgf-move-prev');
	if (movePrev) {
		event.preventDefault();
		handleQuickMove(movePrev);
		return;
	}

	const card = event.target.closest('.bbgf-card');
	if (card) {
		const visitId = Number(card.dataset.visitId);
		if (Number.isNaN(visitId)) {
			return;
		}

		const state = store.getState();
		modalLastFocusedElement = card;

		const boardVisit = findVisitById(state.board, visitId)?.visit ?? null;
		store.setState({
			modal: {
				isOpen: true,
				visitId,
				loading: true,
				visit: boardVisit,
				readOnly: state.board?.readonly || !settings.capabilities?.editVisits,
				activeTab: 'summary',
				isSaving: false,
				form: buildModalFormFromVisit(boardVisit),
				error: '',
			},
		});

		ensureServicesCatalog();

		api.fetchVisit(visitId, {
			view: getActiveViewSlug(store.getState()),
		})
			.then((visit) => {
				store.setState((prev) => ({
					modal: {
						...prev.modal,
						loading: false,
						visit,
						form: buildModalFormFromVisit(visit),
						error: '',
					},
				}));
			})
			.catch((error) => {
				store.setState((prev) => ({
					errors: [...ensureArray(prev.errors), error?.message || strings.loadingError],
					modal: {
						...prev.modal,
						loading: false,
						error: error?.message || strings.loadingError,
					},
				}));
			});
 	}
};

const closeModal = () => {
	store.setState((state) => ({
		modal: {
			...state.modal,
			isOpen: false,
		},
	}));
};

const handleModalContainerClick = (event) => {
	const role = event.target.dataset.role;
	if (role === 'bbgf-modal-tab') {
		const tab = event.target.dataset.tab;
		if (tab) {
			handleModalTabChange(tab);
		}
		return;
	}

	if (role === 'bbgf-modal-save') {
		const section = event.target.dataset.section;
		if (section) {
			handleModalSave(section);
		}
	}
};

const handleModalContainerInput = (event) => {
	const role = event.target.dataset?.role;
	if (role === 'bbgf-modal-input') {
		const field = event.target.dataset.field;
		if (field) {
			updateModalFormField(field, event.target.value);
		}
	}
};

const handleModalContainerChange = (event) => {
	const { dataset, checked } = event.target;
	if (dataset?.role === 'bbgf-modal-service') {
		const serviceId = Number(dataset.serviceId);
		if (!Number.isNaN(serviceId)) {
			toggleModalService(serviceId, checked);
		}
	}
};

const highlightColumn = (stageKey) => {
	if (!boardRootElement) {
		return;
	}

	if (!stageKey) {
		boardRootElement.querySelectorAll('.bbgf-column--drag-hover').forEach((column) => {
			column.classList.remove('bbgf-column--drag-hover');
		});
		currentDragHoverStage = '';
		return;
	}

	if (currentDragHoverStage === stageKey) {
		return;
	}

	boardRootElement.querySelectorAll('.bbgf-column--drag-hover').forEach((column) => {
		column.classList.remove('bbgf-column--drag-hover');
	});

	const target = boardRootElement.querySelector(`.bbgf-column[data-stage="${stageKey}"]`);
	if (target) {
		target.classList.add('bbgf-column--drag-hover');
		currentDragHoverStage = stageKey;
	}
};

const handleDragStart = (event) => {
	const card = event.target.closest('.bbgf-card');
	if (!card) {
		return;
	}

	const state = store.getState();
	if (state.board?.readonly || !settings.capabilities?.moveStages) {
		event.preventDefault();
		return;
	}

	const visitId = Number(card.dataset.visitId);
	const fromStage = safeString(card.dataset.stage);
	if (Number.isNaN(visitId) || !fromStage) {
		event.preventDefault();
		return;
	}

	setDragState({ isDragging: true, visitId, fromStage });

	if (event.dataTransfer) {
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', String(visitId));
	}

	card.classList.add('is-dragging');
	card.setAttribute('aria-grabbed', 'true');
};

const handleDragEnd = (event) => {
	const card = event.target.closest('.bbgf-card');
	if (card) {
		card.classList.remove('is-dragging');
		card.setAttribute('aria-grabbed', 'false');
	}

	setDragState({ isDragging: false, visitId: null, fromStage: '' });
	highlightColumn(null);
};

const handleDragOver = (event) => {
	const state = store.getState();
	if (!state.drag?.isDragging) {
		return;
	}

	const column = event.target.closest('.bbgf-column');
	if (!column) {
		return;
	}

	if (event.dataTransfer) {
		event.dataTransfer.dropEffect = 'move';
	}

	if (!state.board?.readonly && settings.capabilities?.moveStages) {
		event.preventDefault();
		const stageKey = column.dataset.stage;
		if (stageKey && stageKey !== state.drag.fromStage) {
			highlightColumn(stageKey);
		}
	}
};

const handleDragLeave = (event) => {
	const column = event.target.closest('.bbgf-column');
	if (!column) {
		return;
	}

	if (!column.contains(event.relatedTarget)) {
		highlightColumn(null);
	}
};

const handleDrop = (event) => {
	const state = store.getState();
	if (!state.drag?.isDragging) {
		return;
	}

	const column = event.target.closest('.bbgf-column');
	if (!column) {
		return;
	}

	event.preventDefault();

	const stageKey = column.dataset.stage;
	const visitId = Number(event.dataTransfer?.getData('text/plain')) || state.drag.visitId;

	const targetStage = stageKey;
	const fromStage = state.drag.fromStage;
	if (!targetStage || targetStage === fromStage) {
		return;
	}

	highlightColumn(null);
	setDragState({ isDragging: false, visitId: null, fromStage: '' });

	if (!Number.isNaN(visitId)) {
		moveVisitToStage(visitId, targetStage);
	}
};

const handleModalEvents = (root) => {
	if (root.dataset.bbgfModalBound === 'true') {
		return;
	}

	const backdrop = root.querySelector('[data-role="bbgf-modal-backdrop"]');
	const closeButton = root.querySelector('[data-role="bbgf-modal-close"]');

	if (backdrop) {
		backdrop.addEventListener('click', closeModal);
	}

	if (closeButton) {
		closeButton.addEventListener('click', closeModal);
	}

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			const { modal } = store.getState();
			if (modal.isOpen) {
				event.preventDefault();
				closeModal();
			}
		}
	});

	const dialog = root.querySelector('.bbgf-modal__dialog');
	if (dialog) {
		dialog.addEventListener('click', handleModalContainerClick);
		dialog.addEventListener('input', handleModalContainerInput, true);
		dialog.addEventListener('change', handleModalContainerChange);
	}

	root.dataset.bbgfModalBound = 'true';
};

const renderModal = (state, root, copy) => {
	const container = root.querySelector('#bbgf-modal');
	if (!container) {
		return;
	}

	const { modal } = state;
	if (!modal.isOpen) {
		container.setAttribute('hidden', 'hidden');
		const previous = modalLastFocusedElement;
		if (previous && previous.focus) {
			window.requestAnimationFrame(() => previous.focus());
		}
		modalLastFocusedElement = null;
		return;
	}

	container.removeAttribute('hidden');

	const body = container.querySelector('[data-role="bbgf-modal-body"]');
	const tabsNav = container.querySelector('[data-role="bbgf-modal-tabs"]');
	const title = container.querySelector('#bbgf-modal-title');
	const closeButton = container.querySelector('[data-role="bbgf-modal-close"]');

	const tabs = [
		{ id: 'summary', label: copy.modalSummary },
		{ id: 'notes', label: copy.modalNotes },
		{ id: 'services', label: copy.modalServices },
		{ id: 'history', label: copy.modalHistory },
		{ id: 'photos', label: copy.modalPhotos },
	];

	if (tabsNav) {
		tabsNav.innerHTML = tabs
			.map(
				(tab) =>
					`<button type="button" class="bbgf-modal__tab${modal.activeTab === tab.id ? ' is-active' : ''}" data-role="bbgf-modal-tab" data-tab="${tab.id}">${escapeHtml(
						tab.label
					)}</button>`
			)
			.join('');
	}

	let contentHtml = `<p class="bbgf-modal__loading">${copy.modalLoading}</p>`;

	if (modal.loading) {
		contentHtml = `<p class="bbgf-modal__loading">${copy.modalLoading}</p>`;
	} else if (!modal.visit) {
		contentHtml = `<p class="bbgf-modal__loading">${copy.loadingError}</p>`;
	} else {
		const visit = modal.visit;
		const stageLabel = ensureArray(state.board?.stages).find((stage) => stage.key === visit.current_stage)?.label ?? visit.current_stage;
		const clientName = escapeHtml(visit.client?.name ?? copy.unknownClient);
		const guardian = visit.guardian || {};
		const maskGuardian = Boolean(state.board?.visibility?.mask_guardian);
		const servicesSelection = new Set(ensureArray(modal.form.services).map((id) => Number(id)));
		const catalogServices = state.catalog.services;

		if (modal.activeTab === 'summary') {
			const photoData = getVisitPhoto(visit);
			const photoMarkup =
				photoData && photoData.url
					? `<img src="${escapeHtml(photoData.url)}" alt="${escapeHtml(photoData.alt ?? clientName)}">`
					: `<span>${(safeString(visit.client?.name ?? copy.unknownClient).charAt(0) || '🐾').toUpperCase()}</span>`;

			const serviceChips = ensureArray(visit.services)
				.map((service) => {
					const icon = safeString(service?.icon ?? '');
					const label = safeString(service?.name ?? '');
					const text = label ? (icon && icon !== label ? `${icon} ${label}` : label) : icon;
					return text ? `<span class="bbgf-modal-chip">${escapeHtml(text)}</span>` : '';
				})
				.filter(Boolean)
				.join('');
			const flagChips = ensureArray(visit.flags)
				.map((flag) => {
					const emoji = safeString(flag?.emoji ?? '');
					const label = safeString(flag?.name ?? '');
					const text = label ? `${emoji ? `${emoji} ` : ''}${label}` : emoji;
					return text ? `<span class="bbgf-modal-chip bbgf-modal-chip--flag">${escapeHtml(text)}</span>` : '';
				})
				.filter(Boolean)
				.join('');
			const tagsHtml = [serviceChips, flagChips].filter(Boolean).join('');
			const guardianNameParts = [safeString(guardian.first_name ?? ''), safeString(guardian.last_name ?? '')].filter(Boolean);
			const guardianName = guardianNameParts.join(' ').trim();
			const contactPieces = [safeString(guardian.phone ?? ''), safeString(guardian.email ?? '')].filter(Boolean);
			const guardianMetaPieces = [];
			if (maskGuardian) {
				guardianMetaPieces.push(escapeHtml(copy.maskedGuardian));
			} else {
				if (guardianName) {
					guardianMetaPieces.push(escapeHtml(guardianName));
				}
				if (contactPieces.length) {
					guardianMetaPieces.push(contactPieces.map((piece) => escapeHtml(piece)).join(' • '));
				}
			}
			const guardianMeta =
				guardianMetaPieces.length > 0 ? `<p class="bbgf-modal-summary__meta">${guardianMetaPieces.join(' • ')}</p>` : '';
			const checkIn = escapeHtml(formatDateTime(visit.check_in_at) || '');
			const checkOut = escapeHtml(formatDateTime(visit.check_out_at) || '');
			const timerSeconds = toNumber(visit.timer_elapsed_seconds) ?? 0;
			const elapsedDisplay = timerSeconds > 0 ? escapeHtml(formatDuration(timerSeconds)) : '';
			const instructions = escapeHtml(visit.instructions ?? '').replace(/\n/g, '<br>');
			const publicNotes = escapeHtml(visit.public_notes ?? '').replace(/\n/g, '<br>');
			const privateNotes = escapeHtml(visit.private_notes ?? '').replace(/\n/g, '<br>');
			const summaryItems = [];
			summaryItems.push({ label: copy.checkIn, value: checkIn || '-' });
			summaryItems.push({ label: 'Check-out', value: checkOut || '-' });
			const summaryGridHtml = summaryItems
				.map(
					(item) =>
						`<div class="bbgf-summary-item"><span class="bbgf-summary-item__label">${escapeHtml(item.label)}</span><span class="bbgf-summary-item__value">${item.value}</span></div>`
				)
				.join('');
			const noteBlocks = [];
			if (instructions) {
				noteBlocks.push({ heading: 'Instructions', body: instructions });
			}
			if (publicNotes) {
				noteBlocks.push({ heading: 'Public notes', body: publicNotes });
			}
			if (!modal.readOnly && privateNotes) {
				noteBlocks.push({ heading: 'Private notes', body: privateNotes });
			}
			const notesHtml = noteBlocks.length
				? `<div class="bbgf-modal-summary__notes">${noteBlocks
						.map((note) => `<article class="bbgf-modal-note"><h4>${escapeHtml(note.heading)}</h4><p>${note.body}</p></article>`)
						.join('')}</div>`
				: '';

			contentHtml = `
				<section class="bbgf-modal-summary">
					<header class="bbgf-modal-summary__header">
						<div class="bbgf-modal-summary__photo">${photoMarkup}</div>
						<div class="bbgf-modal-summary__primary">
							<h3 class="bbgf-modal-summary__name">${clientName}</h3>
							<p class="bbgf-modal-summary__subtitle">
								<span class="bbgf-modal-summary__stage">${escapeHtml(stageLabel || visit.current_stage || '')}</span>
								${elapsedDisplay ? `<span class="bbgf-modal-summary__elapsed">${elapsedDisplay}</span>` : ''}
							</p>
							${guardianMeta}
						</div>
					</header>
					${tagsHtml ? `<div class="bbgf-modal-summary__tags">${tagsHtml}</div>` : ''}
					${summaryGridHtml ? `<div class="bbgf-modal-summary__grid">${summaryGridHtml}</div>` : ''}
					${notesHtml}
				</section>
			`;
		} else if (modal.activeTab === 'notes') {
			contentHtml = `
				<form class="bbgf-modal-form" data-role="bbgf-modal-notes-form">
					<div class="bbgf-modal-field">
						<label for="bbgf-modal-instructions">${escapeHtml(copy.notes)} (${escapeHtml('Instructions')})</label>
						<textarea id="bbgf-modal-instructions" data-role="bbgf-modal-input" data-field="instructions" ${modal.readOnly ? 'disabled' : ''}></textarea>
					</div>
					<div class="bbgf-modal-field">
						<label for="bbgf-modal-public-notes">${escapeHtml('Public notes')}</label>
						<textarea id="bbgf-modal-public-notes" data-role="bbgf-modal-input" data-field="publicNotes" ${modal.readOnly ? 'disabled' : ''}></textarea>
					</div>
					<div class="bbgf-modal-field">
						<label for="bbgf-modal-private-notes">${escapeHtml('Private notes')}</label>
						<textarea id="bbgf-modal-private-notes" data-role="bbgf-modal-input" data-field="privateNotes" ${modal.readOnly ? 'disabled' : ''}></textarea>
					</div>
					${modal.readOnly ? '' : `<div class="bbgf-modal__actions"><button type="button" class="bbgf-button bbgf-button--primary" data-role="bbgf-modal-save" data-section="notes" ${modal.isSaving ? 'disabled' : ''}>${escapeHtml(modal.isSaving ? copy.modalSaving : copy.modalSave)}</button></div>`}
				</form>
			`;
		} else if (modal.activeTab === 'services') {
			if (!settings.capabilities?.manageServices) {
				const serviceList = ensureArray(visit.services)
					.map((service) => `<li>${escapeHtml(service.name)}</li>`)
					.join('');

				contentHtml = `
					<div class="bbgf-modal-section">
						<p>${escapeHtml('You do not have permission to modify services. Contact an administrator if you need changes.')}</p>
						<ul class="bbgf-modal-list">${serviceList || `<li>${escapeHtml(copy.emptyColumn)}</li>`}</ul>
					</div>
				`;
			} else if (catalogServices.isLoading && !catalogServices.loaded) {
				contentHtml = `<p class="bbgf-modal__loading">${copy.modalLoading}</p>`;
			} else if (catalogServices.error) {
				contentHtml = `<p class="bbgf-modal__error">${escapeHtml(catalogServices.error)}</p>`;
			} else {
				const serviceItems = ensureArray(catalogServices.items)
					.map((service) => {
						const id = Number(service?.id);
						const checked = servicesSelection.has(id) ? 'checked' : '';
						return `
							<label class="bbgf-modal-service">
								<input type="checkbox" data-role="bbgf-modal-service" data-service-id="${id}" ${checked} ${modal.readOnly ? 'disabled' : ''}>
								<span>${escapeHtml(service?.name ?? '')}</span>
							</label>
						`;
					})
					.join('');

				contentHtml = `
					<div class="bbgf-modal-service-list">
						${serviceItems || `<p class="bbgf-modal__loading">${escapeHtml(copy.emptyColumn)}</p>`}
					</div>
					${modal.readOnly ? '' : `<div class="bbgf-modal__actions"><button type="button" class="bbgf-button bbgf-button--primary" data-role="bbgf-modal-save" data-section="services" ${modal.isSaving ? 'disabled' : ''}>${escapeHtml(modal.isSaving ? copy.modalSaving : copy.modalSave)}</button></div>`}
				`;
			}
		} else if (modal.activeTab === 'history') {
			const historyItems = ensureArray(visit.history)
				.map((entry) => {
					const when = formatDateTime(entry.changed_at);
					const from = escapeHtml(entry.from_stage?.label ?? entry.from_stage?.key ?? '');
					const to = escapeHtml(entry.to_stage?.label ?? entry.to_stage?.key ?? '');
					const comment = escapeHtml(entry.comment ?? '').replace(/\n/g, '<br>');
					const duration = entry.elapsed_seconds ? formatDuration(entry.elapsed_seconds) : '';
					return `
						<li>
							<div class="bbgf-history-entry">
								<strong>${when}</strong>
								<p>${from} → ${to}</p>
								${duration ? `<p class="bbgf-history-duration">${escapeHtml(duration)}</p>` : ''}
								${comment ? `<p>${comment}</p>` : ''}
							</div>
						</li>
					`;
				})
				.join('');

			contentHtml = `
				<ul class="bbgf-modal-history">
					${historyItems || `<li>${escapeHtml(copy.modalNoHistory)}</li>`}
				</ul>
			`;
		} else if (modal.activeTab === 'photos') {
			const photos = ensureArray(visit.photos);
			let photoItems = photos
				.map((photo) => {
					const url = escapeHtml(photo.url ?? '');
					const alt = escapeHtml(photo.alt ?? clientName);
					return `<figure class="bbgf-modal-photo"><img src="${url}" alt="${alt}"><figcaption>${alt}</figcaption></figure>`;
				})
				.join('');

			if (!photoItems) {
				const placeholder = getPlaceholderPhoto(visit);
				if (placeholder && placeholder.url) {
					const alt = escapeHtml(placeholder.alt ?? clientName);
					photoItems = `<figure class="bbgf-modal-photo"><img src="${escapeHtml(placeholder.url)}" alt="${alt}"><figcaption>${alt}</figcaption></figure>`;
				}
			}

			contentHtml = `
				<div class="bbgf-modal-photos">
					${photoItems || `<p class="bbgf-modal__loading">${escapeHtml(copy.modalNoPhotos)}</p>`}
				</div>
			`;
		}

		if (modal.error) {
			contentHtml = `<div class="bbgf-modal__error">${escapeHtml(modal.error)}</div>${contentHtml}`;
		}
	}

	body.innerHTML = contentHtml;

	if (modal.activeTab === 'notes') {
		const instructionsField = body.querySelector('[data-field="instructions"]');
		const publicField = body.querySelector('[data-field="publicNotes"]');
		const privateField = body.querySelector('[data-field="privateNotes"]');
		if (instructionsField) {
			instructionsField.value = modal.form.instructions ?? '';
		}
		if (publicField) {
			publicField.value = modal.form.publicNotes ?? '';
		}
		if (privateField) {
			privateField.value = modal.form.privateNotes ?? '';
		}
	}

	if (modal.activeTab === 'services') {
		const checkboxes = body.querySelectorAll('[data-role="bbgf-modal-service"]');
		checkboxes.forEach((input) => {
			const id = Number(input.dataset.serviceId);
			input.checked = servicesSelection.has(id);
			if (modal.isSaving) {
				input.setAttribute('disabled', 'disabled');
			}
		});
	}

	if (title) {
		const name = modal.visit?.client?.name ? `: ${modal.visit.client.name}` : '';
		title.textContent = `${copy.modalTitle}${name}`;
	}

	if (modal.readOnly) {
		container.classList.add('bbgf-modal--readonly');
	} else {
		container.classList.remove('bbgf-modal--readonly');
	}

	const badge = container.querySelector('[data-role="bbgf-modal-readonly"]');
	if (badge) {
		if (modal.readOnly) {
			badge.removeAttribute('hidden');
		} else {
			badge.setAttribute('hidden', 'hidden');
		}
	}

	const activeElement = document.activeElement;
	if (closeButton && (!container.contains(activeElement) || activeElement === container)) {
		window.requestAnimationFrame(() => closeButton.focus());
	}
};

const setupDragAndDrop = (root, state) => {
	if (currentPresentation.mode === 'display') {
		return;
	}

	const cards = root.querySelectorAll('.bbgf-card');
	const readonly = state.board?.readonly;

	cards.forEach((card) => {
		if (readonly || !settings.capabilities?.moveStages) {
			card.removeAttribute('draggable');
			card.setAttribute('aria-grabbed', 'false');
		} else {
			card.setAttribute('draggable', 'true');
			if (!card.hasAttribute('aria-grabbed')) {
				card.setAttribute('aria-grabbed', 'false');
			}
		}
	});
};

const handleBoardKeyDown = (event) => {
	const card = event.target.closest('.bbgf-card');
	if (!card) {
		return;
	}

	if (event.target.closest('.bbgf-card-actions button')) {
		return;
	}

	if (event.key === 'Enter' || event.key === ' ') {
		event.preventDefault();
		card.click();
		return;
	}

	if (event.key === 'ArrowLeft') {
		event.preventDefault();
		moveVisitWithDirection(card, 'prev');
		return;
	}

	if (event.key === 'ArrowRight') {
		event.preventDefault();
		moveVisitWithDirection(card, 'next');
	}
};

document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('bbgf-board-root');
	if (!root) {
		return;
	}

	boardRootElement = root;
	document.addEventListener('fullscreenchange', updateFullscreenButton);

	if (settings.context?.view) {
		root.dataset.activeView = settings.context.view;
	}

	renderBoard(store.getState(), root, settings, strings);
	updateToolbarStates(root, store.getState());
	attachToolbarHandlers(root);
	setupLastUpdatedTicker(root);
	renderModal(store.getState(), root, strings);
	handleModalEvents(root);
	setupDragAndDrop(root, store.getState());

	store.subscribe((state) => {
		renderBoard(state, root, settings, strings);
		updateToolbarStates(root, state);
		attachToolbarHandlers(root);
		setupLastUpdatedTicker(root);
		renderModal(state, root, strings);
		setupDragAndDrop(root, state);
	});

	window.bbgfBoardSettings = settings;
	window.bbgfBoardStore = store;
	window.bbgfBoardApi = api;
	window.bbgfBoardRefresh = () => loadBoard({ reason: 'manual', forceFull: true });

	root.addEventListener('click', handleBoardClick);
	root.addEventListener('keydown', handleBoardKeyDown);
	root.addEventListener('dragstart', handleDragStart);
	root.addEventListener('dragend', handleDragEnd);
	root.addEventListener('dragover', handleDragOver);
	root.addEventListener('dragleave', handleDragLeave);
	root.addEventListener('drop', handleDrop);

	loadBoard({ reason: 'initial', forceFull: !settings.initialBoard });
});
