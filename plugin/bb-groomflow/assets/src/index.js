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
	modalVisit: settings.strings?.modalVisit ?? 'Visit',
	modalHistory: settings.strings?.modalHistory ?? 'History',
	modalPhotos: settings.strings?.modalPhotos ?? 'Photos',
	modalNoPrevious: settings.strings?.modalNoPrevious ?? 'No previous visits found.',
	modalUploadPhoto: settings.strings?.modalUploadPhoto ?? 'Upload photo',
	modalVisibleToGuardian: settings.strings?.modalVisibleToGuardian ?? 'Visible to guardian',
	modalViewPhoto: settings.strings?.modalViewPhoto ?? 'View full size',
	modalSave: settings.strings?.modalSave ?? 'Save changes',
	modalSaving: settings.strings?.modalSaving ?? 'Saving…',
	modalCheckout: settings.strings?.modalCheckout ?? 'Check out',
	modalCheckedOut: settings.strings?.modalCheckedOut ?? 'Checked out',
	modalCheckoutAt: settings.strings?.modalCheckoutAt ?? 'Checked out at',
	modalPreparingUpload: settings.strings?.modalPreparingUpload ?? 'Preparing photos…',
	modalNoHistory: settings.strings?.modalNoHistory ?? 'No history recorded yet.',
	modalNoPhotos: settings.strings?.modalNoPhotos ?? 'No photos uploaded for this visit.',
	searchPlaceholder: settings.strings?.searchPlaceholder ?? 'Search clients, guardians, services…',
	moveSuccess: settings.strings?.moveSuccess ?? 'Visit moved.',
	fullscreen: settings.strings?.fullscreen ?? 'Fullscreen',
	exitFullscreen: settings.strings?.exitFullscreen ?? 'Exit fullscreen',
	autoRefresh: settings.strings?.autoRefresh ?? 'Auto-refresh in',
	maskedGuardian: settings.strings?.maskedGuardian ?? 'Guardian hidden for lobby view',
	errorFetching: settings.strings?.errorFetching ?? 'Unable to refresh the board. Please try again.',
	activeVisits: settings.strings?.activeVisits ?? 'Active visits',
	overdueVisits: settings.strings?.overdueVisits ?? 'Overdue',
	flaggedVisits: settings.strings?.flaggedVisits ?? 'Flagged',
	filtersActive: settings.strings?.filtersActive ?? 'Filters applied',
	clearFilters: settings.strings?.clearFilters ?? 'Clear filters',
	guardianLabel: settings.strings?.guardianLabel ?? 'Guardian',
	stageLabel: settings.strings?.stageLabel ?? 'Stage',
	stagesLabel: settings.strings?.stagesLabel ?? 'Stages',
	intakeTitle: settings.strings?.intakeTitle ?? 'Check in a client',
	intakeSearchLabel: settings.strings?.intakeSearchLabel ?? 'Search clients or guardians',
	intakeNoResults: settings.strings?.intakeNoResults ?? 'No matches found. Add a new client below.',
	intakeGuardian: settings.strings?.intakeGuardian ?? 'Guardian',
	intakeClient: settings.strings?.intakeClient ?? 'Client',
	intakeVisit: settings.strings?.intakeVisit ?? 'Visit details',
	intakeSubmit: settings.strings?.intakeSubmit ?? 'Create visit',
	intakeSaving: settings.strings?.intakeSaving ?? 'Creating visit…',
	intakeSuccess: settings.strings?.intakeSuccess ?? 'Visit created and added to the board.',
	intakeSearchHint: settings.strings?.intakeSearchHint ?? 'Search by client, guardian, phone, or email.',
};

const presentation = settings.presentation || {};
const appearance = settings.appearance || {};
const appearanceColors = appearance.colors || {};
const allowedMetadataBlocks = ['meta', 'services', 'flags', 'notes'];

const resolveMetadataOrder = () => {
	const configured = Array.isArray(appearance.metadata_order) ? appearance.metadata_order : [];
	const normalized = configured
		.map((item) => {
			const key = String(item || '').trim().toLowerCase();
			if (['summary', 'checkin', 'check_in'].includes(key)) {
				return 'meta';
			}
			return key;
		})
		.filter((item) => allowedMetadataBlocks.includes(item));

	if (normalized.length) {
		return normalized.filter((item, index) => normalized.indexOf(item) === index);
	}

	return allowedMetadataBlocks;
};

const showServicesSetting = appearance.show_services !== false;
const showFlagsSetting = appearance.show_flags !== false;
const showNotesSetting = appearance.show_notes !== false;

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

	// Remove any changed visits from the current map so we can merge in the new records.
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
		const existingVisits = ensureArray(existing?.visits).map((visit) => cloneVisit(visit));
		const patchVisits = ensureArray(stage.visits).map((visit) => cloneVisit(visit));

		const mergedStage = {
			...(existing ?? {}),
			...stage,
			visits: [...existingVisits, ...patchVisits],
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
		return '>1m';
	}

	const seconds = Math.max(0, Math.floor(totalSeconds));
	const hours = Math.floor(seconds / 3600);
	const minutes = Math.floor((seconds % 3600) / 60);
	const remainingSeconds = seconds % 60;

	if (seconds < 60) {
		return '>1m';
	}

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

const loadImageForCanvas = async (file) => {
	if (!file) {
		throw new Error('Missing file');
	}

	if (window.createImageBitmap) {
		try {
			const bitmap = await createImageBitmap(file);
			return {
				source: bitmap,
				width: bitmap.width,
				height: bitmap.height,
				cleanup: () => {
					if (typeof bitmap.close === 'function') {
						bitmap.close();
					}
				},
			};
		} catch (error) {
			// Fall back to Image element loader below.
		}
	}

	return new Promise((resolve, reject) => {
		const url = URL.createObjectURL(file);
		const image = new Image();
		image.onload = () => {
			URL.revokeObjectURL(url);
			resolve({
				source: image,
				width: image.naturalWidth || image.width,
				height: image.naturalHeight || image.height,
				cleanup: () => {},
			});
		};
		image.onerror = (error) => {
			URL.revokeObjectURL(url);
			reject(error || new Error('Unable to load image'));
		};
		image.src = url;
	});
};

const normalizeImageFile = async (file, options = {}) => {
	if (!(file instanceof File) || typeof file.type !== 'string' || !file.type.startsWith('image/')) {
		return file;
	}

	const maxDimension = Number(options.maxDimension ?? 1600);
	const maxBytes = Number(options.maxBytes ?? 1.8 * 1024 * 1024);
	const baseQuality = Number(options.quality ?? 0.82);
	const image = await loadImageForCanvas(file);
	const largestSide = Math.max(image.width, image.height);

	if (!largestSide) {
		image.cleanup();
		return file;
	}

	const scale = largestSide > maxDimension ? maxDimension / largestSide : 1;
	const targetWidth = Math.max(1, Math.round(image.width * scale));
	const targetHeight = Math.max(1, Math.round(image.height * scale));
	const canvas = document.createElement('canvas');
	canvas.width = targetWidth;
	canvas.height = targetHeight;

	const ctx = canvas.getContext('2d');
	if (!ctx) {
		return file;
	}

	ctx.drawImage(image.source, 0, 0, targetWidth, targetHeight);

	const mimeType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
	const toBlob = (quality) =>
		new Promise((resolve, reject) => {
			canvas.toBlob(
				(blob) => {
					if (blob) {
						resolve(blob);
					} else {
						reject(new Error('Unable to process image'));
					}
				},
				mimeType,
				quality
			);
		});

	let quality = mimeType === 'image/png' ? 0.92 : baseQuality;
	let blob = await toBlob(quality);
	let attempts = 0;

	while (blob && blob.size > maxBytes && quality > 0.5 && attempts < 5) {
		quality = Math.max(0.5, quality - 0.1);
		blob = await toBlob(quality);
		attempts += 1;
	}

	if (blob && blob.size > maxBytes && scale === 1) {
		const compressionScale = Math.sqrt(maxBytes / blob.size);
		const scaledWidth = Math.max(1, Math.round(targetWidth * compressionScale));
		const scaledHeight = Math.max(1, Math.round(targetHeight * compressionScale));
		canvas.width = scaledWidth;
		canvas.height = scaledHeight;
		ctx.clearRect(0, 0, scaledWidth, scaledHeight);
		ctx.drawImage(image.source, 0, 0, scaledWidth, scaledHeight);
		blob = await toBlob(quality);
	}

	if (!blob) {
		image.cleanup();
		return file;
	}

	image.cleanup();

	const baseName = file.name.replace(/\.[^.]+$/, '') || 'photo';
	const extension = mimeType === 'image/png' ? 'png' : 'jpg';

	return new File([blob], `${baseName}.${extension}`, {
		type: mimeType,
		lastModified: Date.now(),
	});
};

const buildServiceSummary = (services) =>
	ensureArray(services)
		.map((service) => safeString(service?.name))
		.filter(Boolean)
		.join(', ');

const applyAppearanceTheme = (root) => {
	if (!root) {
		return;
	}

	const setColor = (token, value) => {
		if (typeof value === 'string' && value) {
			root.style.setProperty(token, value);
		}
	};

	setColor('--bbgf-accent', appearanceColors.accent);
	setColor('--bbgf-card-bg', appearanceColors.card);
	setColor('--bbgf-column-bg', appearanceColors.column);
	setColor('--bbgf-background-start', appearanceColors.background_start);
	setColor('--bbgf-background-end', appearanceColors.background_end);
	setColor('--bbgf-text', appearanceColors.text);
	if (appearanceColors.text) {
		setColor('--bbgf-muted', appearanceColors.text);
		setColor('--bbgf-muted-soft', appearanceColors.text);
	}
	setColor('--bbgf-warning', appearanceColors.timer_warning);
	setColor('--bbgf-critical', appearanceColors.timer_critical);
	setColor('--bbgf-flag', appearanceColors.flag);
};

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

const resolveTimerSeconds = (visit) => {
	const raw = toNumber(visit?.timer_elapsed_seconds);
	const maxReasonableSeconds = 60 * 60 * 24 * 14; // 14 days.

	if (Number.isFinite(raw) && raw >= 0 && raw <= maxReasonableSeconds) {
		return raw;
	}

	const startIso = visit?.timer_started_at || visit?.check_in_at;
	if (startIso) {
		const startDate = new Date(startIso);
		if (!Number.isNaN(startDate.getTime())) {
			return Math.max(0, Math.round((Date.now() - startDate.getTime()) / 1000));
		}
	}

	return Number.isFinite(raw) && raw >= 0 ? raw : 0;
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
	const fallbackNonce = window?.wpApiSettings?.nonce || '';
	const buildHeaders = () => {
		const headers = {
			Accept: 'application/json',
		};

		if (rest.nonce || fallbackNonce) {
			headers['X-WP-Nonce'] = rest.nonce || fallbackNonce;
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

	const checkoutVisit = async (visitId, payload = {}) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const url = buildUrl(`${endpoint}/${encodeURIComponent(visitId)}/checkout`);
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				...buildHeaders(),
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				comment: payload.comment ?? '',
			}),
		});

		if (!response.ok) {
			let message = `Checkout failed with status ${response.status}`;
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

	const createVisit = async (payload = {}) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const url = buildUrl(endpoint);
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				...buildHeaders(),
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload),
		});

		if (!response.ok) {
			let message = `Create failed with status ${response.status}`;
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

	const searchIntake = async (query = '') => {
		const endpoint = rest.endpoints?.intakeSearch;
		if (!endpoint) {
			throw new Error('Intake search endpoint unavailable');
		}

		const url = buildUrl(endpoint, {
			query,
		});

		const response = await fetch(url, {
			method: 'GET',
			headers: buildHeaders(),
			credentials: 'same-origin',
		});

		if (!response.ok) {
			throw new Error(`Search failed with status ${response.status}`);
		}

		const data = await response.json();
		return data?.items ?? [];
	};

	const addPhoto = async (visitId, file, visibleToGuardian = true) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const url = buildUrl(`${endpoint}/${encodeURIComponent(visitId)}/photo`);
		const formData = new FormData();
		formData.append('visible_to_guardian', visibleToGuardian ? '1' : '0');
		formData.append('file', file);

		const response = await fetch(url, {
			method: 'POST',
			headers: buildHeaders(),
			credentials: 'same-origin',
			body: formData,
		});

		if (!response.ok) {
			let message = `Photo upload failed with status ${response.status}`;
			try {
				const data = await response.json();
				if (data?.message) {
					message = data.message;
				}
			} catch {
				// ignore
			}

			throw new Error(message);
		}

		return response.json();
	};

	const updatePhoto = async (visitId, photoId, payload = {}) => {
		const endpoint = rest.endpoints?.visits;
		if (!endpoint) {
			throw new Error('Visit endpoint unavailable');
		}

		const url = buildUrl(`${endpoint}/${encodeURIComponent(visitId)}/photo/${encodeURIComponent(photoId)}`);
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
			let message = `Photo update failed with status ${response.status}`;
			try {
				const data = await response.json();
				if (data?.message) {
					message = data.message;
				}
			} catch {
				// ignore
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
		checkoutVisit,
		updateVisit,
		createVisit,
		searchIntake,
		addPhoto,
		updatePhoto,
		fetchServices,
	};
};

const createCardElement = (visit, stage, options) => {
	const { copy, previous, next, canMove, pendingMoves, readonly, draggingVisitId, presentation = {} } = options;

	const stageKey = safeString(stage.key ?? stage.stage_key ?? '');
	const card = document.createElement('article');
	card.className = 'bbgf-card';
	card.dataset.visitId = String(visit.id ?? '');
	card.dataset.stage = stageKey;
	card.dataset.updatedAt = safeString(visit.updated_at ?? '');
	card.setAttribute('role', 'listitem');
	card.setAttribute('aria-label', safeString(visit.client?.name ?? copy.unknownClient));

	if (!readonly && settings.capabilities?.moveStages) {
		card.setAttribute('draggable', 'true');
		card.classList.add('bbgf-card--draggable');
	} else {
		card.removeAttribute('draggable');
		card.classList.remove('bbgf-card--draggable');
	}

	const visitIdNumber = Number(visit.id);
	const isPending = pendingMoves?.has(visitIdNumber);
	const isDragging = draggingVisitId !== null && visitIdNumber === Number(draggingVisitId);
	card.classList.toggle('bbgf-card--pending', Boolean(isPending));
	card.classList.toggle('is-dragging', Boolean(isDragging));
	card.setAttribute('aria-grabbed', isDragging ? 'true' : 'false');

	const timerSeconds = resolveTimerSeconds(visit);
	const timerState = getTimerState(timerSeconds, stage.timer_thresholds ?? {});
	card.classList.add(`bbgf-card--timer-${timerState}`);

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

	const topRow = document.createElement('div');
	topRow.className = 'bbgf-card-top';
	topRow.appendChild(photoWrapper);

	const info = document.createElement('div');
	info.className = 'bbgf-card-info';

	const name = document.createElement('p');
	name.className = 'bbgf-card-name';
	name.textContent = safeString(visit.client?.name ?? copy.unknownClient);
	info.appendChild(name);

	const flags = ensureArray(visit.flags);
	if (flags.length && showFlagsSetting) {
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

		info.appendChild(flagsWrapper);
	}

	topRow.appendChild(info);
	body.appendChild(topRow);

	const timerRow = document.createElement('div');
	timerRow.className = 'bbgf-card-meta';

	const timer = document.createElement('span');
	timer.className = 'bbgf-card-timer';
	timer.dataset.state = timerState;
	timer.dataset.seconds = String(timerSeconds);
	timer.dataset.baseSeconds = String(timerSeconds);
	timer.dataset.yellow = String(toNumber(stage.timer_thresholds?.yellow) ?? '');
	timer.dataset.red = String(toNumber(stage.timer_thresholds?.red) ?? '');
	timer.textContent = formatDuration(timerSeconds);
	timerRow.appendChild(timer);

	const services = ensureArray(visit.services);
	if (services.length && showServicesSetting) {
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

	const footer = document.createElement('div');
	footer.className = 'bbgf-card-footer';
	footer.appendChild(timerRow);

	const checkInText = formatCheckIn(visit.check_in_at);
	if (checkInText) {
		const checkInSpan = document.createElement('span');
		checkInSpan.className = 'bbgf-card-checkin';
		checkInSpan.textContent = checkInText;
		footer.appendChild(checkInSpan);
	}

	body.appendChild(footer);

	card.appendChild(body);

	return card;
};

const createColumnElement = (stage, options) => {
	const { copy, previous, next, canMove, pendingMoves, readonly, draggingVisitId, searchActive, presentation = {} } = options;

	const stageKey = safeString(stage.key ?? stage.stage_key ?? '');
	const label = safeString(stage.label ?? stageKey);
	const visits = ensureArray(stage.visits);
	const count = visits.length;

	const column = document.createElement('section');
	column.className = 'bbgf-column';
	column.dataset.stage = stageKey;
	column.dataset.visitCount = String(count);
	column.setAttribute('role', 'listitem');
	column.setAttribute('aria-label', label);
	column.setAttribute('aria-dropeffect', readonly ? 'none' : 'move');

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
	countSpan.textContent = String(count);
	countSpan.setAttribute('aria-label', String(count));
	title.appendChild(countSpan);

	header.appendChild(title);

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
			const card = createCardElement(visit, stage, {
				copy,
				previous,
				next,
				canMove,
				pendingMoves,
				readonly,
				draggingVisitId,
				presentation,
			});
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

const buildActionDock = (state, board, copy, ui) => {
	return null;
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

	const preservedModal = root.querySelector('#bbgf-modal');
	const preservedIntakeModal = root.querySelector('#bbgf-intake-modal');

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

	const columnsWrapper = document.createElement('div');
	columnsWrapper.className = 'bbgf-board';
	columnsWrapper.setAttribute('role', 'list');

	const filtersActive = false;
	const baseStages = ensureArray(board.stages);

	const filterVisit = (visit) => {
		if (!visit) {
			return false;
		}

		return true;
	};

	const stages = baseStages.map((stage) => ({
		...stage,
		visits: ensureArray(stage.visits).filter(filterVisit),
	}));

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
	startTimerTicker(root);

	const existingControls = root.querySelector('.bbgf-floating-controls');
	if (!existingControls) {
		const controls = document.createElement('div');
		controls.className = 'bbgf-floating-controls';

		if (settings.capabilities?.editVisits && currentPresentation.mode !== 'display') {
			const intakeButton = document.createElement('button');
			intakeButton.type = 'button';
			intakeButton.className = 'bbgf-icon-button bbgf-intake-button';
			intakeButton.dataset.role = 'bbgf-intake-open';
			intakeButton.setAttribute('aria-label', strings.intakeTitle);
			intakeButton.innerHTML = `<span aria-hidden="true">＋</span><span class="bbgf-intake-button__label">${escapeHtml(strings.checkIn)}</span>`;
			controls.appendChild(intakeButton);
		}

		const refreshButton = document.createElement('button');
		refreshButton.type = 'button';
		refreshButton.className = 'bbgf-icon-button bbgf-refresh-button';
		refreshButton.setAttribute('aria-label', copy.refresh);
		refreshButton.textContent = '⟳';
		if (state.isLoading) {
			refreshButton.setAttribute('disabled', 'disabled');
			refreshButton.classList.add('is-disabled');
		}
		controls.appendChild(refreshButton);

		if (ui.showFullscreen) {
			const fullscreenToggle = document.createElement('button');
			fullscreenToggle.type = 'button';
			fullscreenToggle.className = 'bbgf-icon-button bbgf-toolbar-fullscreen';
			fullscreenToggle.dataset.role = 'bbgf-fullscreen-toggle';
			fullscreenToggle.setAttribute('aria-label', copy.fullscreen);
			fullscreenToggle.textContent = '⛶';
			controls.appendChild(fullscreenToggle);
		}

		root.appendChild(controls);
	}

	const existingModal = preservedModal || root.querySelector('#bbgf-modal');
	if (existingModal) {
		// Preserve the existing modal DOM to avoid losing form state while the board re-renders.
		root.appendChild(existingModal);
	} else {
		const modal = document.createElement('div');
		modal.id = 'bbgf-modal';
		modal.setAttribute('hidden', 'hidden');
		modal.className = 'bbgf-modal';
		modal.innerHTML = `
			<div class="bbgf-modal__backdrop" data-role="bbgf-modal-backdrop"></div>
			<div class="bbgf-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbgf-modal-title">
				<header class="bbgf-modal__header">
					<div class="bbgf-modal__heading">
						<h2 id="bbgf-modal-title" class="bbgf-modal__title">${copy.modalTitle}</h2>
						<span class="bbgf-modal__badge" data-role="bbgf-modal-readonly" hidden>${copy.modalReadOnly}</span>
					</div>
					<div class="bbgf-modal__nav" data-role="bbgf-modal-nav"></div>
					<button type="button" class="bbgf-modal__close" data-role="bbgf-modal-close" aria-label="${copy.modalClose}">&times;</button>
				</header>
				<nav class="bbgf-modal__tabs" data-role="bbgf-modal-tabs"></nav>
				<div class="bbgf-modal__body" data-role="bbgf-modal-body">
					<p class="bbgf-modal__loading">${copy.modalLoading}</p>
				</div>
			</div>
		`.trim();
		root.appendChild(modal);
	}

	const intakeModalExisting = preservedIntakeModal || root.querySelector('#bbgf-intake-modal');
	if (intakeModalExisting) {
		root.appendChild(intakeModalExisting);
	} else {
		const intakeModal = document.createElement('div');
		intakeModal.id = 'bbgf-intake-modal';
		intakeModal.setAttribute('hidden', 'hidden');
		intakeModal.className = 'bbgf-modal bbgf-intake-modal';
		intakeModal.innerHTML = `
			<div class="bbgf-modal__backdrop" data-role="bbgf-intake-close"></div>
			<div class="bbgf-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbgf-intake-title">
				<header class="bbgf-modal__header">
					<div class="bbgf-modal__heading">
						<h2 id="bbgf-intake-title" class="bbgf-modal__title">${escapeHtml(strings.intakeTitle)}</h2>
					</div>
					<button type="button" class="bbgf-modal__close" data-role="bbgf-intake-close" aria-label="${escapeHtml(strings.modalClose)}">&times;</button>
				</header>
				<div class="bbgf-modal__body bbgf-intake-body" data-role="bbgf-intake-body">
					<p class="bbgf-modal__loading">${escapeHtml(strings.loading)}</p>
				</div>
				<div class="bbgf-modal__actions bbgf-intake-actions" data-role="bbgf-intake-actions"></div>
			</div>
		`.trim();
		root.appendChild(intakeModal);
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

	fullscreenButton = root.querySelector('[data-role="bbgf-fullscreen-toggle"]') || fullscreenButton;
	updateFullscreenButton();
};

const setupLastUpdatedTicker = (root) => {
	const targets = Array.from(root.querySelectorAll('[data-role="bbgf-last-updated"]'));
	if (!targets.length) {
		if (root._bbgfLastUpdatedInterval) {
			window.clearInterval(root._bbgfLastUpdatedInterval);
			root._bbgfLastUpdatedInterval = null;
		}
		return;
	}

	const update = () => {
		targets.forEach((node) => {
			const base = node.dataset.timestamp;
			const humanized = humanizeRelativeTime(base);
			node.textContent = base ? `${strings.lastUpdated} ${humanized || formatDateForLastUpdated(base)}` : strings.lastUpdated;
		});

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

const startTimerTicker = (root) => {
	// Timers are now server-driven and refreshed via polling; render once without intervals.
	const timers = root.querySelectorAll('.bbgf-card-timer');
	timers.forEach((node) => {
		const total = Math.max(0, Number(node.dataset.baseSeconds ?? node.dataset.seconds ?? 0));
		node.textContent = formatDuration(total);

		const yellow = toNumber(node.dataset.yellow);
		const red = toNumber(node.dataset.red);
		const state = getTimerState(total, { yellow, red });
		node.dataset.state = state;
		const card = node.closest('.bbgf-card');
		if (card) {
			card.classList.remove('bbgf-card--timer-on-track', 'bbgf-card--timer-warning', 'bbgf-card--timer-critical');
			card.classList.remove('bbgf-card--overdue', 'bbgf-card--capacity-warning');
			card.classList.add(`bbgf-card--timer-${state}`);
			if (state === 'critical') {
				card.classList.add('bbgf-card--overdue');
			} else if (state === 'warning') {
				card.classList.add('bbgf-card--capacity-warning');
			}
		}
	});
};

const announce = (message) => {
	if (message && window.wp?.a11y?.speak) {
		window.wp.a11y.speak(message, 'polite');
	}
};

const resolveDefaultStageKey = (board) => {
	const stages = ensureArray(board?.stages);
	if (!stages.length) {
		return '';
	}
	const raw = stages[0]?.key ?? stages[0]?.stage_key ?? stages[0]?.id ?? '';
	return safeString(raw).toLowerCase();
};

const getDefaultIntakeState = (board) => ({
	isOpen: false,
	isSaving: false,
	error: '',
	activeTab: 'search',
	searchQuery: '',
	searchResults: [],
	selected: null,
	guardian: {
		id: null,
		first_name: '',
		last_name: '',
		email: '',
		phone: '',
		preferred_contact: '',
		notes: '',
	},
	client: {
		id: null,
		name: '',
		breed: '',
		weight: '',
		temperament: '',
		notes: '',
		guardian_id: null,
	},
	visit: {
		view_id: board?.view?.id ?? null,
		current_stage: resolveDefaultStageKey(board),
		instructions: '',
		public_notes: '',
	},
});

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
		pendingUploads: [],
		isPreparingUploads: false,
	},
	intake: getDefaultIntakeState(settings.initialBoard || null),
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

const preparePhotoUploads = async (files) => {
	const token = ++photoPreparationToken;
	const list = Array.isArray(files) ? files : [];

	setModalState((modal) => ({
		...modal,
		isPreparingUploads: true,
		error: '',
		pendingUploads: [],
	}));

	if (!list.length) {
		setModalState((modal) => ({
			...modal,
			isPreparingUploads: false,
			pendingUploads: [],
		}));
		return;
	}

	try {
		const normalized = [];
		for (const file of list) {
			// eslint-disable-next-line no-await-in-loop
			normalized.push(await normalizeImageFile(file));
		}

		if (token !== photoPreparationToken) {
			return;
		}

		setModalState((modal) => ({
			...modal,
			pendingUploads: normalized,
			isPreparingUploads: false,
		}));
	} catch (error) {
		if (token !== photoPreparationToken) {
			return;
		}

		setModalState((modal) => ({
			...modal,
			pendingUploads: [],
			isPreparingUploads: false,
			error: error?.message || strings.loadingError,
		}));
	}
};

const setIntakeState = (updater) => {
	store.setState((state) => {
		const intake = typeof updater === 'function' ? updater(state.intake, state) : { ...state.intake, ...updater };
		return {
			...state,
			intake,
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

const handleModalMove = async (direction) => {
	const state = store.getState();
	const { modal, board } = state;

	if (!modal.visit || modal.isSaving || modal.readOnly || board?.readonly || !settings.capabilities?.moveStages) {
		return;
	}

	const neighbors = getStageNeighbors(board, modal.visit.current_stage);
	const target = direction === 'prev' ? neighbors.prev?.key : neighbors.next?.key;

	if (!target || target === modal.visit.current_stage) {
		return;
	}

	setModalState({ isSaving: true, error: '' });

	try {
		await moveVisitToStage(modal.visitId, target);
		await openVisitModal(modal.visitId, { tab: modal.activeTab || 'summary' });
	} catch (error) {
		const message = error?.message || strings.errorFetching || strings.loadingError;
		setModalState({ error: message });
		announce(message);
	} finally {
		setModalState({ isSaving: false });
	}
};

const handleModalCheckout = async () => {
	const state = store.getState();
	const { modal, board } = state;

	if (!modal.visitId || modal.isSaving || modal.readOnly || board?.readonly) {
		return;
	}

	setModalState({ isSaving: true, error: '' });

	try {
		await api.checkoutVisit(modal.visitId, {});
		closeModal();
		refreshBoardAfterModalSave();
	} catch (error) {
		const message = error?.message || strings.loadingError;
		setModalState({ error: message, isSaving: false });
		announce(message);
		return;
	}

	setModalState((current) => ({
		...current,
		isSaving: false,
	}));
};

const refreshBoardAfterModalSave = () => {
	loadBoard({ reason: 'modal-save', forceFull: true });
};

const handleModalPhotoUpload = async (button) => {
	const container = button.closest('[data-role="bbgf-photo-upload"]');
	const fileInput = container?.querySelector('[data-role="bbgf-photo-file"]');
	const visibleCheckbox = container?.querySelector('[data-role="bbgf-photo-visible"]');
	const { modal } = store.getState();
	const files = ensureArray(modal.pendingUploads);
	const visible = visibleCheckbox ? Boolean(visibleCheckbox.checked) : true;

	if (modal.isPreparingUploads) {
		setModalState((current) => ({
			...current,
			error: strings.modalPreparingUpload || strings.loadingError,
		}));
		return;
	}

	if (!files.length || !modal.visitId) {
		return;
	}

	setModalState({ isSaving: true, error: '' });

	try {
		for (const file of files) {
			// eslint-disable-next-line no-await-in-loop
			await api.addPhoto(modal.visitId, file, visible);
		}
		if (fileInput) {
			fileInput.value = '';
		}
		setModalState((current) => ({
			...current,
			pendingUploads: [],
			isPreparingUploads: false,
		}));
		await openVisitModal(modal.visitId, { tab: modal.activeTab || 'photos' });
		refreshBoardAfterModalSave();
	} catch (error) {
		const message = error?.message || strings.loadingError;
		setModalState({ error: message });
		announce(message);
	} finally {
		setModalState({ isSaving: false });
	}
};

const handlePhotoVisibilityChange = async (photoId, visible) => {
	const { modal } = store.getState();
	if (!modal.visitId) {
		return;
	}

	try {
		setModalState({ isSaving: true, error: '' });
		await api.updatePhoto(modal.visitId, photoId, { visible_to_guardian: visible });
		await openVisitModal(modal.visitId, { tab: modal.activeTab || 'photos' });
		refreshBoardAfterModalSave();
	} catch (error) {
		const message = error?.message || strings.loadingError;
		setModalState({ error: message });
		announce(message);
	} finally {
		setModalState({ isSaving: false });
	}
};

const setPhotoAsPrimary = async (photoId) => {
	const { modal } = store.getState();
	if (!modal.visitId) {
		return;
	}

	try {
		setModalState({ isSaving: true, error: '' });
		await api.updatePhoto(modal.visitId, photoId, { is_primary: true });
		await openVisitModal(modal.visitId, { tab: modal.activeTab || 'photos' });
		refreshBoardAfterModalSave();
	} catch (error) {
		const message = error?.message || strings.loadingError;
		setModalState({ error: message });
		announce(message);
	} finally {
		setModalState({ isSaving: false });
	}
};

const openPhotoLightbox = (url) => {
	if (!url) {
		return;
	}

	const existing = document.querySelector('.bbgf-lightbox');
	if (existing) {
		existing.remove();
	}

	const lightbox = document.createElement('div');
	lightbox.className = 'bbgf-lightbox';
	lightbox.innerHTML = `
		<div class="bbgf-lightbox__backdrop" data-role="bbgf-lightbox-close"></div>
		<div class="bbgf-lightbox__body">
			<button type="button" class="bbgf-button bbgf-lightbox__close" data-role="bbgf-lightbox-close">${escapeHtml(strings.modalClose)}</button>
			<img src="${escapeHtml(url)}" alt="">
		</div>
	`;

	lightbox.addEventListener('click', (event) => {
		if (event.target.dataset.role === 'bbgf-lightbox-close') {
			lightbox.remove();
		}
	});

	document.body.appendChild(lightbox);
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
	// Fixed 30s polling for server-driven timers.
	return 30 * 1000;
};

let pollTimer = null;
let modalLastFocusedElement = null;
let boardRootElement = null;
let currentDragHoverStage = '';
let toastTimer = null;
let fullscreenButton = null;
let currentPresentation = { mode: 'interactive' };
let lastBoardStateRef = null;
let lastModalSnapshot = null;
let lastIntakeSnapshot = null;
let lastCatalogRef = null;
let lastErrorsRef = null;
let photoPreparationToken = 0;
let activeTouchDrag = null;
let intakeSearchTimer = null;

const updateFullscreenButton = () => {
	if (!fullscreenButton) {
		return;
	}

	const isFullscreen = Boolean(document.fullscreenElement);
	fullscreenButton.textContent = isFullscreen ? '⤢' : '⛶';
	fullscreenButton.setAttribute('title', isFullscreen ? strings.exitFullscreen : strings.fullscreen);
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

	const intakeTrigger = event.target.closest('[data-role="bbgf-intake-open"]');
	if (intakeTrigger) {
		event.preventDefault();
		openIntakeModal();
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

		modalLastFocusedElement = card;
		openVisitModal(visitId, { tab: 'summary' });
	}
};

const resetIntakeState = () => {
	const board = store.getState().board;
	setIntakeState({ ...getDefaultIntakeState(board), isOpen: false });
};

const openIntakeModal = () => {
	if (!settings.capabilities?.editVisits) {
		return;
	}
	const board = store.getState().board;
	setIntakeState({ ...getDefaultIntakeState(board), isOpen: true, activeTab: 'search' });
};

const closeIntakeModal = () => {
	setIntakeState((current) => ({
		...current,
		isOpen: false,
		isSaving: false,
		error: '',
	}));
};

const applyIntakeSelection = (item) => {
	const board = store.getState().board;
	const defaults = getDefaultIntakeState(board);
	const guardian = item.guardian || {};
	const client = item.client || {};

	setIntakeState((current) => ({
		...current,
		selected: item,
		guardian: {
			id: guardian.id ?? null,
			first_name: guardian.first_name ?? '',
			last_name: guardian.last_name ?? '',
			email: guardian.email ?? '',
			phone: guardian.phone_mobile ?? guardian.phone_alt ?? guardian.phone ?? '',
			preferred_contact: guardian.preferred_contact ?? '',
			notes: guardian.notes ?? '',
		},
		client: {
			...current.client,
			id: client.id ?? null,
			name: client.name ?? '',
			breed: client.breed ?? '',
			weight: client.weight ?? '',
			temperament: client.temperament ?? '',
			notes: client.notes ?? '',
			guardian_id: client.guardian_id ?? guardian.id ?? null,
		},
		visit: {
			...current.visit,
			view_id: current.visit.view_id ?? defaults.visit.view_id,
			current_stage: current.visit.current_stage || defaults.visit.current_stage,
		},
		activeTab: 'guardian',
	}));
};

const updateIntakeField = (section, field, value) => {
	if (!section || !field) {
		return;
	}
	setIntakeState((current) => ({
		...current,
		[section]: {
			...(current[section] || {}),
			[field]: value,
		},
	}));
};

const handleIntakeSearch = (term) => {
	if (intakeSearchTimer) {
		window.clearTimeout(intakeSearchTimer);
	}

	const trimmed = safeString(term || '').trim();

	setIntakeState((current) => ({
		...current,
		searchQuery: term,
		searchResults: trimmed.length < 2 ? [] : current.searchResults,
	}));

	if (trimmed.length < 2) {
		return;
	}

	intakeSearchTimer = window.setTimeout(async () => {
		try {
			const results = await api.searchIntake(term);
			setIntakeState((current) => ({
				...current,
				searchResults: results,
				error: '',
			}));
		} catch (error) {
			setIntakeState((current) => ({
				...current,
				error: error?.message || strings.loadingError,
			}));
		}
	}, 200);
};

const handleIntakeSubmit = async () => {
	const state = store.getState();
	const board = state.board;
	const intake = state.intake;
	const guardian = intake.guardian || {};
	const client = intake.client || {};
	const visit = intake.visit || {};

	const guardianFirst = safeString(guardian.first_name || '');
	const guardianLast = safeString(guardian.last_name || '');
	const clientName = safeString(client.name || '');
	const stageKey = safeString(visit.current_stage || resolveDefaultStageKey(board));
	const viewId = Number(visit.view_id || board?.view?.id || 0) || null;

	if (!guardianFirst || !guardianLast) {
		setIntakeState((current) => ({ ...current, error: 'Guardian first and last name are required.' }));
		return;
	}

	if (!clientName) {
		setIntakeState((current) => ({ ...current, error: 'Client name is required.' }));
		return;
	}

	if (!stageKey) {
		setIntakeState((current) => ({ ...current, error: strings.stageLabel || 'Stage is required.' }));
		return;
	}

	setIntakeState((current) => ({ ...current, isSaving: true, error: '' }));

	const guardianPayload = {
		id: guardian.id ?? intake.selected?.guardian?.id ?? null,
		first_name: guardianFirst,
		last_name: guardianLast,
		email: safeString(guardian.email || ''),
		phone: safeString(guardian.phone || guardian.phone_mobile || ''),
		preferred_contact: safeString(guardian.preferred_contact || ''),
		notes: safeString(guardian.notes || ''),
	};

	const weightValue = safeString(client.weight || '');
	const clientPayload = {
		id: client.id ?? intake.selected?.client?.id ?? null,
		name: clientName,
		breed: safeString(client.breed || ''),
		weight: weightValue,
		temperament: safeString(client.temperament || ''),
		notes: safeString(client.notes || ''),
		guardian_id: client.guardian_id ?? guardianPayload.id ?? null,
	};

	const payload = {
		current_stage: stageKey,
		view_id: viewId,
		status: 'in_progress',
		check_in_at: new Date().toISOString(),
		instructions: safeString(visit.instructions || ''),
		public_notes: safeString(visit.public_notes || ''),
		client: clientPayload,
		guardian: guardianPayload,
	};

	try {
		await api.createVisit(payload);
		announce(strings.intakeSuccess);
		resetIntakeState();
		refreshBoardAfterModalSave();
	} catch (error) {
		setIntakeState((current) => ({
			...current,
			isSaving: false,
			error: error?.message || strings.loadingError,
		}));
		return;
	}

	setIntakeState((current) => ({
		...getDefaultIntakeState(board),
		isOpen: false,
	}));
};

const closeModal = () => {
	photoPreparationToken += 1;
	store.setState((state) => ({
		modal: {
			...state.modal,
			isOpen: false,
			isSaving: false,
			isPreparingUploads: false,
			pendingUploads: [],
		},
	}));
};

const handleModalContainerClick = (event) => {
	const role = event.target.dataset.role;
	if (role === 'bbgf-modal-close') {
		closeModal();
		return;
	}

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
		return;
	}

	if (role === 'bbgf-modal-move') {
		const direction = event.target.dataset.direction;
		if (direction) {
			handleModalMove(direction);
		}
		return;
	}

	if (role === 'bbgf-modal-checkout') {
		handleModalCheckout();
		return;
	}

	if (role === 'bbgf-upload-photo') {
		handleModalPhotoUpload(event.target);
		return;
	}

	if (role === 'bbgf-photo-primary') {
		const photoId = Number(event.target.dataset.photoId);
		if (!Number.isNaN(photoId)) {
			setPhotoAsPrimary(photoId);
		}
		return;
	}

	if (role === 'bbgf-photo-cancel') {
		const upload = event.target.closest('[data-role="bbgf-photo-upload"]');
		const fileInput = upload?.querySelector('[data-role="bbgf-photo-file"]');
		if (fileInput) {
			fileInput.value = '';
		}
		photoPreparationToken += 1;
		setModalState((modal) => ({
			...modal,
			pendingUploads: [],
			isPreparingUploads: false,
		}));
		return;
	}

	if (event.target.closest('[data-role="bbgf-photo-visibility"]')) {
		return;
	}

	if (event.target.closest('[data-role="bbgf-photo-file"]')) {
		return;
	}

	const preview = event.target.closest('[data-role="bbgf-photo-preview"]');
	if (preview) {
		const url = preview.dataset?.photoUrl;
		if (url) {
			openPhotoLightbox(url);
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

	if (dataset?.role === 'bbgf-photo-visibility') {
		event.stopPropagation();
		const photoId = Number(dataset.photoId);
		if (!Number.isNaN(photoId)) {
			handlePhotoVisibilityChange(photoId, checked);
		}
	}

	if (dataset?.role === 'bbgf-photo-file') {
		const files = event.target.files ? Array.from(event.target.files) : [];
		preparePhotoUploads(files);
	}
};

const openVisitModal = async (visitId, options = {}) => {
	if (!Number.isFinite(Number(visitId))) {
		return;
	}

	const state = store.getState();
	const boardVisit = findVisitById(state.board, visitId)?.visit ?? null;
	const nextTab = options.tab || 'summary';

	store.setState({
		modal: {
			isOpen: true,
			visitId,
			loading: true,
			visit: boardVisit,
			readOnly: state.board?.readonly || !settings.capabilities?.editVisits,
			activeTab: nextTab,
			isSaving: false,
			form: buildModalFormFromVisit(boardVisit),
			error: '',
			pendingUploads: [],
			isPreparingUploads: false,
		},
	});

	ensureServicesCatalog();

	try {
		const visit = await api.fetchVisit(visitId, {
			view: getActiveViewSlug(store.getState()),
		});

		store.setState((prev) => ({
			modal: {
				...prev.modal,
				loading: false,
				visit,
				form: buildModalFormFromVisit(visit),
				error: '',
			},
		}));
	} catch (error) {
		store.setState((prev) => ({
			errors: [...ensureArray(prev.errors), error?.message || strings.loadingError],
			modal: {
				...prev.modal,
				loading: false,
				error: error?.message || strings.loadingError,
			},
		}));
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

const getTouchTargetColumn = (touchEvent) => {
	const point = touchEvent.changedTouches?.[0];
	if (!point) {
		return null;
	}
	const element = document.elementFromPoint(point.clientX, point.clientY);
	return element ? element.closest('.bbgf-column') : null;
};

const handleTouchStart = (event) => {
	if (!settings.capabilities?.moveStages) {
		return;
	}

	const card = event.target.closest('.bbgf-card');
	if (!card) {
		return;
	}

	const visitId = Number(card.dataset.visitId);
	const fromStage = safeString(card.dataset.stage);
	if (Number.isNaN(visitId) || !fromStage) {
		return;
	}

	activeTouchDrag = { visitId, fromStage };
	highlightColumn(null);
};

const handleTouchMove = (event) => {
	if (!activeTouchDrag) {
		return;
	}
	event.preventDefault();
	const column = getTouchTargetColumn(event);
	if (column) {
		const stageKey = safeString(column.dataset.stage);
		if (stageKey && stageKey !== activeTouchDrag.fromStage) {
			highlightColumn(stageKey);
		}
	}
};

const handleTouchEnd = (event) => {
	if (!activeTouchDrag) {
		return;
	}
	const column = getTouchTargetColumn(event);
	const targetStage = safeString(column?.dataset.stage ?? '');
	if (targetStage && targetStage !== activeTouchDrag.fromStage) {
		moveVisitToStage(activeTouchDrag.visitId, targetStage);
	}
	highlightColumn(null);
	activeTouchDrag = null;
};

const handleModalEvents = (root) => {
	const container = root.querySelector('#bbgf-modal');
	if (!container || container.dataset.bound === 'true') {
		return;
	}

	const backdrop = container.querySelector('[data-role="bbgf-modal-backdrop"]');
	const closeButton = container.querySelector('[data-role="bbgf-modal-close"]');

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

	const dialog = container.querySelector('.bbgf-modal__dialog');
	if (dialog) {
		dialog.addEventListener('click', handleModalContainerClick);
		dialog.addEventListener('input', handleModalContainerInput, true);
		dialog.addEventListener('change', handleModalContainerChange);
	}

	container.dataset.bound = 'true';
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
	const nav = container.querySelector('[data-role="bbgf-modal-nav"]');

	const tabs = [
		{ id: 'summary', label: copy.modalSummary },
		{ id: 'notes', label: copy.modalNotes },
		{ id: 'services', label: copy.modalServices },
		{ id: 'visit', label: copy.modalVisit },
		{ id: 'history', label: copy.modalHistory },
		{ id: 'photos', label: copy.modalPhotos },
	];

	if (tabsNav) {
		tabsNav.innerHTML = tabs
			.map((tab) => {
				const isActive = modal.activeTab === tab.id;
				return `<button type="button" class="bbgf-modal__tab${isActive ? ' is-active' : ''}" data-role="bbgf-modal-tab" data-tab="${tab.id}" aria-selected="${isActive ? 'true' : 'false'}">${escapeHtml(
					tab.label
				)}</button>`;
			})
			.join('');
	}

	if (nav) {
		if (modal.visit) {
			const neighbors = getStageNeighbors(state.board, modal.visit.current_stage);
			const stageLabel =
				ensureArray(state.board?.stages).find((stage) => stage.key === modal.visit.current_stage)?.label ?? modal.visit.current_stage;
			const canEdit = settings.capabilities?.editVisits && !state.board?.readonly && !modal.readOnly;
			const canMove = settings.capabilities?.moveStages && !state.board?.readonly && !modal.readOnly && !modal.visit.check_out_at;
			const prevButton =
				canMove && neighbors.prev
					? `<button type="button" class="bbgf-button bbgf-button--ghost" data-role="bbgf-modal-move" data-direction="prev">${escapeHtml(copy.movePrev)}</button>`
					: '';
			const nextButton =
				canMove && neighbors.next
					? `<button type="button" class="bbgf-button bbgf-button--primary" data-role="bbgf-modal-move" data-direction="next">${escapeHtml(copy.moveNext)}</button>`
					: '';
			const checkoutButton =
				canEdit && !modal.visit.check_out_at
					? `<button type="button" class="bbgf-button bbgf-button--critical" data-role="bbgf-modal-checkout" ${modal.isSaving ? 'disabled' : ''}>${escapeHtml(
							copy.modalCheckout
						)}</button>`
					: '';
			const checkoutTime = modal.visit.check_out_at ? formatDateTime(modal.visit.check_out_at) : '';
			const checkoutMeta =
				modal.visit.check_out_at
					? `<span class="bbgf-modal__nav-status">${escapeHtml(copy.modalCheckedOut || copy.modalCheckoutAt)}${
							checkoutTime ? ` • ${escapeHtml(checkoutTime)}` : ''
						}</span>`
					: '';
			nav.innerHTML = `
				<div class="bbgf-modal__nav-actions">
					${prevButton}
					${nextButton}
					${checkoutButton}
				</div>
				<div class="bbgf-modal__nav-info">
					<span>${escapeHtml(stageLabel || copy.stageLabel)}</span>
					${checkoutMeta}
				</div>
			`;
		} else {
			nav.innerHTML = '';
		}
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
			summaryItems.push({ label: copy.modalCheckoutAt || 'Check-out', value: checkOut || '-' });
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
		} else if (modal.activeTab === 'visit') {
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
		} else if (modal.activeTab === 'history') {
			const previousItems = ensureArray(visit.previous_visits)
				.map((entry) => {
					const when = formatDateTime(entry.check_in_at || entry.created_at);
					const stage = escapeHtml(entry.stage ?? '');
					const instructions = escapeHtml(entry.instructions ?? '').replace(/\n/g, '<br>');
					const publicNotes = escapeHtml(entry.public_notes ?? '').replace(/\n/g, '<br>');
					const privateNotes = escapeHtml(entry.private_notes ?? '').replace(/\n/g, '<br>');

					return `
						<li>
							<div class="bbgf-history-entry">
								<strong>${when || copy.modalHistory}</strong>
								${stage ? `<p>${stage}</p>` : ''}
								${instructions ? `<p class="bbgf-history-duration">${instructions}</p>` : ''}
								${publicNotes ? `<p>${publicNotes}</p>` : ''}
								${privateNotes ? `<p><em>${privateNotes}</em></p>` : ''}
							</div>
						</li>
					`;
				})
				.join('');

			contentHtml = `
				<ul class="bbgf-modal-history">
					${previousItems || `<li>${escapeHtml(copy.modalNoPrevious)}</li>`}
				</ul>
			`;
		} else if (modal.activeTab === 'photos') {
			const photos = ensureArray(visit.photos);
			const pendingUploads = ensureArray(modal.pendingUploads);
			const isPreparingUploads = Boolean(modal.isPreparingUploads);
			const pendingList = pendingUploads.length
				? `<ul class="bbgf-photo-upload__list">${pendingUploads
						.map((file) => `<li>${escapeHtml(file.name || 'Untitled file')}</li>`)
						.join('')}</ul>`
				: `<p class="bbgf-photo-upload__hint">${escapeHtml(
						isPreparingUploads ? copy.modalPreparingUpload : 'Select images to preview filenames before uploading.'
					)}</p>`;
			let photoItems = photos
				.map((photo) => {
					const url = escapeHtml(photo.url ?? '');
					const alt = escapeHtml(photo.alt ?? clientName);
					const visible = photo.visible_to_guardian !== false;
					const photoId = Number(photo.id);
					const isPrimary = photo.is_primary === true;
					const visibilityToggle =
						settings.capabilities?.editVisits && !state.board?.readonly && !modal.readOnly && Number.isFinite(photoId)
							? `<label class="bbgf-photo-visibility"><input type="checkbox" data-role="bbgf-photo-visibility" data-photo-id="${photoId}" ${visible ? 'checked' : ''}>${escapeHtml(copy.modalVisibleToGuardian)}</label>`
							: '';
					const primaryAction =
						settings.capabilities?.editVisits && !state.board?.readonly && !modal.readOnly && Number.isFinite(photoId)
							? `<button type="button" class="bbgf-button bbgf-button--ghost bbgf-photo-primary" data-role="bbgf-photo-primary" data-photo-id="${photoId}" ${isPrimary ? 'disabled' : ''}>${escapeHtml(
									isPrimary ? 'Main photo' : 'Set as main photo'
							  )}</button>`
							: '';

					return `<figure class="bbgf-modal-photo" data-role="bbgf-photo-preview" data-photo-id="${photoId}" data-photo-url="${url}" aria-label="${escapeHtml(copy.modalViewPhoto)}">
						<div class="bbgf-modal-photo__thumb">
							<img src="${url}" alt="${alt}">
							${isPrimary ? '<span class="bbgf-photo-badge bbgf-photo-badge--primary">Main</span>' : ''}
							${visible ? '' : '<span class="bbgf-photo-badge">Hidden</span>'}
						</div>
						<figcaption>${alt}${visibilityToggle || primaryAction ? `<div class="bbgf-photo-actions">${primaryAction}${visibilityToggle}</div>` : ''}</figcaption>
					</figure>`;
				})
				.join('');

			if (!photoItems) {
				const placeholder = getPlaceholderPhoto(visit);
				if (placeholder && placeholder.url) {
					const alt = escapeHtml(placeholder.alt ?? clientName);
					photoItems = `<figure class="bbgf-modal-photo"><img src="${escapeHtml(placeholder.url)}" alt="${alt}"><figcaption>${alt}</figcaption></figure>`;
				}
			}

			const photosFooter = `
				<div class="bbgf-modal__actions bbgf-modal__actions--photos">
					<span class="bbgf-modal__hint">Changes save automatically.</span>
					<button type="button" class="bbgf-button bbgf-button--ghost" data-role="bbgf-modal-close">${escapeHtml(copy.modalClose)}</button>
				</div>
			`;

			contentHtml = `
				${settings.capabilities?.editVisits && !state.board?.readonly && !modal.readOnly ? `
				<div class="bbgf-photo-upload" data-role="bbgf-photo-upload">
					<label class="bbgf-photo-upload__file">
						<input type="file" accept="image/*" data-role="bbgf-photo-file" multiple>
						<span>${escapeHtml(copy.modalUploadPhoto)}</span>
					</label>
					<label class="bbgf-photo-upload__visibility">
						<input type="checkbox" data-role="bbgf-photo-visible" checked>
						<span>${escapeHtml(copy.modalVisibleToGuardian)}</span>
					</label>
					<div class="bbgf-photo-upload__pending">
						${pendingList}
					</div>
					<div class="bbgf-photo-upload__actions">
						<button type="button" class="bbgf-button bbgf-button--ghost" data-role="bbgf-photo-cancel" ${
							modal.isSaving || isPreparingUploads ? 'disabled' : pendingUploads.length ? '' : 'disabled'
						}>Clear</button>
						<button type="button" class="bbgf-button bbgf-button--primary" data-role="bbgf-upload-photo" ${
							isPreparingUploads || modal.isSaving || !pendingUploads.length ? 'disabled' : ''
						}>${escapeHtml(
							isPreparingUploads
								? copy.modalPreparingUpload
								: pendingUploads.length
									? `Upload ${pendingUploads.length} file${pendingUploads.length === 1 ? '' : 's'}`
									: copy.modalUploadPhoto
						)}</button>
					</div>
				</div>` : ''}
				<div class="bbgf-modal-photos">
					${photoItems || `<p class="bbgf-modal__loading">${escapeHtml(copy.modalNoPhotos)}</p>`}
				</div>
				${photosFooter}
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

const renderIntakeModal = (state, root, copy) => {
	const container = root.querySelector('#bbgf-intake-modal');
	if (!container) {
		return;
	}

	const { intake, board } = state;
	if (!intake.isOpen) {
		container.setAttribute('hidden', 'hidden');
		return;
	}

	container.removeAttribute('hidden');

	const body = container.querySelector('[data-role="bbgf-intake-body"]');
	const actions = container.querySelector('[data-role="bbgf-intake-actions"]');
	if (!body || !actions) {
		return;
	}

	const activeElement = container.contains(document.activeElement) ? document.activeElement : null;
	const focusMeta = activeElement
		? {
				role: activeElement.dataset?.role,
				section: activeElement.dataset?.section,
				field: activeElement.dataset?.field,
				selection:
					typeof activeElement.selectionStart === 'number'
						? { start: activeElement.selectionStart, end: activeElement.selectionEnd }
						: null,
		  }
		: null;

	const stages = ensureArray(board?.stages);
	const viewLabel = safeString(board?.view?.name ?? board?.view?.slug ?? '');
	const stageOptions = stages
		.map((stage) => {
			const key = safeString(stage.key ?? stage.stage_key ?? '');
			const label = safeString(stage.label ?? key);
			const selected = intake.visit.current_stage === key ? 'selected' : '';
			return `<option value="${escapeHtml(key)}" ${selected}>${escapeHtml(label)}</option>`;
		})
		.join('');

	const results = ensureArray(intake.searchResults);
	const resultItems = results
		.map((item, index) => {
			const client = item.client || {};
			const guardian = item.guardian || {};
			const clientName = safeString(client.name || '');
			const guardianName =
				guardian && (guardian.first_name || guardian.last_name)
					? `${safeString(guardian.first_name)} ${safeString(guardian.last_name)}`.trim()
					: '';
			const contactPieces = [guardian.phone_mobile || guardian.phone_alt || '', guardian.email || ''].filter(Boolean);
			const meta = [client.breed || '', guardianName].filter(Boolean).join(' • ');
			const contact = contactPieces.join(' • ');
			return `
				<article class="bbgf-intake-result">
					<div class="bbgf-intake-result__body">
						<p class="bbgf-intake-result__title">${escapeHtml(clientName || copy.unknownClient)}</p>
						${meta ? `<p class="bbgf-intake-result__meta">${escapeHtml(meta)}</p>` : ''}
						${contact ? `<p class="bbgf-intake-result__contact">${escapeHtml(contact)}</p>` : ''}
					</div>
					<button type="button" class="bbgf-button bbgf-button--ghost" data-role="bbgf-intake-select" data-result-index="${index}">${escapeHtml(
						copy.checkIn
					)}</button>
				</article>
			`;
		})
		.join('');

	const guardian = intake.guardian || {};
	const client = intake.client || {};
	const visit = intake.visit || {};
	const trimmedQuery = safeString(intake.searchQuery || '').trim();
	const searchReady = trimmedQuery.length >= 2;
	const tabs = [
		{ id: 'search', label: copy.intakeSearchLabel },
		{ id: 'guardian', label: copy.intakeGuardian },
		{ id: 'client', label: copy.intakeClient },
		{ id: 'visit', label: copy.intakeVisit },
	];
	const activeTab = tabs.some((tab) => tab.id === intake.activeTab) ? intake.activeTab : 'search';
	const tabsHtml = tabs
		.map((tab) => {
			const isActive = tab.id === activeTab;
			return `<button type="button" class="bbgf-modal__tab${isActive ? ' is-active' : ''}" data-role="bbgf-intake-tab" data-tab="${tab.id}" aria-selected="${isActive ? 'true' : 'false'}">${escapeHtml(
				tab.label
			)}</button>`;
		})
		.join('');

	const selectedBadge = intake.selected
		? `<div class="bbgf-intake-selected">Using ${escapeHtml(intake.selected.client?.name ?? copy.unknownClient)}${
				intake.selected.guardian ? ` • ${escapeHtml(intake.selected.guardian?.first_name ?? '')} ${escapeHtml(intake.selected.guardian?.last_name ?? '')}` : ''
		  } <button type="button" class="bbgf-chip-clear" data-role="bbgf-intake-clear" aria-label="${escapeHtml(copy.clearFilters || 'Clear')}">×</button></div>`
		: '';

	const resultsBlock = searchReady
		? resultItems || `<p class="bbgf-modal__loading">${escapeHtml(copy.intakeNoResults)}</p>`
		: `<p class="bbgf-modal__hint">${escapeHtml('Start typing at least 2 characters to search.')}</p>`;

	const searchPanel = `
		<section class="bbgf-intake-panel bbgf-intake-panel--search" role="tabpanel" aria-label="${escapeHtml(copy.intakeSearchLabel)}">
			<label class="bbgf-field-label">${escapeHtml(copy.intakeSearchLabel)}</label>
			<div class="bbgf-intake-search">
				<input type="search" data-role="bbgf-intake-search" value="${escapeHtml(intake.searchQuery)}" placeholder="${escapeHtml(copy.intakeSearchHint)}" aria-label="${escapeHtml(
		copy.intakeSearchLabel
	)}">
			</div>
			<div class="bbgf-intake-results">
				${resultsBlock}
			</div>
		</section>
	`;

	const guardianPanel = `
		<section class="bbgf-intake-panel" role="tabpanel" aria-label="${escapeHtml(copy.intakeGuardian)}">
			<h3>${escapeHtml(copy.intakeGuardian)}</h3>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('First name')}</label>
				<input type="text" data-role="bbgf-intake-field" data-section="guardian" data-field="first_name" value="${escapeHtml(guardian.first_name ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Last name')}</label>
				<input type="text" data-role="bbgf-intake-field" data-section="guardian" data-field="last_name" value="${escapeHtml(guardian.last_name ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Email')}</label>
				<input type="email" data-role="bbgf-intake-field" data-section="guardian" data-field="email" value="${escapeHtml(guardian.email ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Mobile')}</label>
				<input type="tel" data-role="bbgf-intake-field" data-section="guardian" data-field="phone" value="${escapeHtml(guardian.phone ?? guardian.phone_mobile ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Preferred contact')}</label>
				<select data-role="bbgf-intake-field" data-section="guardian" data-field="preferred_contact">
					${['', 'email', 'phone_mobile', 'sms']
						.map((option) => {
							const selected = safeString(option) === safeString(guardian.preferred_contact ?? '') ? 'selected' : '';
							const label = option ? option.replace('_', ' ') : 'None';
							return `<option value="${escapeHtml(option)}" ${selected}>${escapeHtml(label)}</option>`;
						})
						.join('')}
				</select>
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Notes')}</label>
				<textarea data-role="bbgf-intake-field" data-section="guardian" data-field="notes">${escapeHtml(guardian.notes ?? '')}</textarea>
			</div>
		</section>
	`;

	const clientPanel = `
		<section class="bbgf-intake-panel" role="tabpanel" aria-label="${escapeHtml(copy.intakeClient)}">
			<h3>${escapeHtml(copy.intakeClient)}</h3>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Name')}</label>
				<input type="text" data-role="bbgf-intake-field" data-section="client" data-field="name" value="${escapeHtml(client.name ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Breed')}</label>
				<input type="text" data-role="bbgf-intake-field" data-section="client" data-field="breed" value="${escapeHtml(client.breed ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Weight')}</label>
				<input type="text" inputmode="decimal" data-role="bbgf-intake-field" data-section="client" data-field="weight" value="${escapeHtml(client.weight ?? '')}" placeholder="lbs">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Temperament')}</label>
				<input type="text" data-role="bbgf-intake-field" data-section="client" data-field="temperament" value="${escapeHtml(client.temperament ?? '')}">
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml(copy.notes)}</label>
				<textarea data-role="bbgf-intake-field" data-section="client" data-field="notes">${escapeHtml(client.notes ?? '')}</textarea>
			</div>
		</section>
	`;

	const visitPanel = `
		<section class="bbgf-intake-panel" role="tabpanel" aria-label="${escapeHtml(copy.intakeVisit)}">
			<h3>${escapeHtml(copy.intakeVisit)}</h3>
			<div class="bbgf-intake-field">
				<label>${escapeHtml(copy.stageLabel)}</label>
				<select data-role="bbgf-intake-field" data-section="visit" data-field="current_stage">
					${stageOptions || `<option value="">${escapeHtml(copy.stageLabel)}</option>`}
				</select>
			</div>
			${viewLabel ? `<p class="bbgf-intake-view-hint">${escapeHtml(`Will appear on the ${viewLabel} board`)}</p>` : ''}
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Instructions')}</label>
				<textarea data-role="bbgf-intake-field" data-section="visit" data-field="instructions">${escapeHtml(visit.instructions ?? '')}</textarea>
			</div>
			<div class="bbgf-intake-field">
				<label>${escapeHtml('Public notes')}</label>
				<textarea data-role="bbgf-intake-field" data-section="visit" data-field="public_notes">${escapeHtml(visit.public_notes ?? '')}</textarea>
			</div>
		</section>
	`;

	const panels = {
		search: searchPanel,
		guardian: guardianPanel,
		client: clientPanel,
		visit: visitPanel,
	};

	body.innerHTML = `
		<div class="bbgf-modal__tabs bbgf-intake-tabs" role="tablist">
			${tabsHtml}
		</div>
		${selectedBadge ? `<div class="bbgf-intake-selected-wrap">${selectedBadge}</div>` : ''}
		<div class="bbgf-intake-panel-wrap" id="bbgf-intake-panel-${escapeHtml(activeTab)}">
			${panels[activeTab] ?? ''}
		</div>
	`;

	actions.innerHTML = `
		${intake.error ? `<div class="bbgf-modal__error">${escapeHtml(intake.error)}</div>` : ''}
		<div class="bbgf-modal__actions">
			<button type="button" class="bbgf-button bbgf-button--ghost" data-role="bbgf-intake-close" ${intake.isSaving ? 'disabled' : ''}>${escapeHtml(copy.modalClose)}</button>
			<button type="button" class="bbgf-button bbgf-button--primary" data-role="bbgf-intake-submit" ${intake.isSaving ? 'disabled' : ''}>${escapeHtml(
		intake.isSaving ? copy.intakeSaving : copy.intakeSubmit
	)}</button>
		</div>
	`;

	const restoreIntakeFocus = () => {
		if (focusMeta?.role) {
			let selector = `[data-role="${focusMeta.role}"]`;
			if (focusMeta.section) {
				selector += `[data-section="${focusMeta.section}"]`;
			}
			if (focusMeta.field) {
				selector += `[data-field="${focusMeta.field}"]`;
			}
			let target = body.querySelector(selector);
			if (!target) {
				target = body.querySelector(`[data-role="${focusMeta.role}"]`);
			}

			if (target && typeof target.focus === 'function') {
				target.focus({ preventScroll: true });
				if (focusMeta.selection && typeof target.setSelectionRange === 'function') {
					const { start, end } = focusMeta.selection;
					target.setSelectionRange(start, end);
				} else if (typeof target.setSelectionRange === 'function' && typeof target.value === 'string') {
					const end = target.value.length;
					target.setSelectionRange(end, end);
				}
			}
			return;
		}

		if (intake.activeTab === 'search') {
			const searchInput = body.querySelector('[data-role="bbgf-intake-search"]');
			if (searchInput && typeof searchInput.focus === 'function') {
				searchInput.focus({ preventScroll: true });
				if (typeof searchInput.setSelectionRange === 'function') {
					const end = searchInput.value.length;
					searchInput.setSelectionRange(end, end);
				}
			}
		}
	};

	restoreIntakeFocus();
};

const handleIntakeClick = (event) => {
	const role = event.target.dataset?.role;
	if (role === 'bbgf-intake-tab') {
		const tab = safeString(event.target.dataset.tab);
		const allowedTabs = ['search', 'guardian', 'client', 'visit'];
		const nextTab = allowedTabs.includes(tab) ? tab : 'search';
		setIntakeState((current) => ({
			...current,
			activeTab: nextTab,
		}));
		return;
	}

	if (role === 'bbgf-intake-close') {
		closeIntakeModal();
		return;
	}

	if (role === 'bbgf-intake-select') {
		const index = Number(event.target.dataset.resultIndex);
		const results = store.getState().intake.searchResults || [];
		const selected = Number.isInteger(index) ? results[index] : null;
		if (selected) {
			applyIntakeSelection(selected);
		}
		return;
	}

	if (role === 'bbgf-intake-clear') {
		setIntakeState((current) => ({
			...current,
			selected: null,
			client: { ...(current.client || {}), id: null, guardian_id: null },
			guardian: { ...(current.guardian || {}), id: null },
		}));
		return;
	}

	if (role === 'bbgf-intake-submit') {
		handleIntakeSubmit();
	}
};

const handleIntakeInput = (event) => {
	const role = event.target.dataset?.role;
	if (role === 'bbgf-intake-search') {
		handleIntakeSearch(event.target.value || '');
		return;
	}

	if (role === 'bbgf-intake-field') {
		const section = event.target.dataset.section;
		const field = event.target.dataset.field;
		updateIntakeField(section, field, event.target.value);
	}
};

const handleIntakeChange = (event) => {
	const role = event.target.dataset?.role;
	if (role === 'bbgf-intake-field') {
		const section = event.target.dataset.section;
		const field = event.target.dataset.field;
		updateIntakeField(section, field, event.target.value);
	}
};

const handleIntakeEvents = (root) => {
	const container = root.querySelector('#bbgf-intake-modal');
	if (!container || container.dataset.bound === 'true') {
		return;
	}

	container.addEventListener('click', handleIntakeClick);
	container.addEventListener('input', handleIntakeInput, true);
	container.addEventListener('change', handleIntakeChange);

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			const { intake } = store.getState();
			if (intake.isOpen) {
				event.preventDefault();
				closeIntakeModal();
			}
		}
	});

	container.dataset.bound = 'true';
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
	applyAppearanceTheme(boardRootElement);
	document.addEventListener('fullscreenchange', updateFullscreenButton);

	if (settings.context?.view) {
		root.dataset.activeView = settings.context.view;
	}

	const initialStateSnapshot = store.getState();
	renderBoard(initialStateSnapshot, root, settings, strings);
	updateToolbarStates(root, initialStateSnapshot);
	attachToolbarHandlers(root);
	setupLastUpdatedTicker(root);
	renderModal(initialStateSnapshot, root, strings);
	handleModalEvents(root);
	renderIntakeModal(initialStateSnapshot, root, strings);
	handleIntakeEvents(root);
	setupDragAndDrop(root, initialStateSnapshot);
	lastBoardStateRef = {
		board: initialStateSnapshot.board,
		isLoading: initialStateSnapshot.isLoading,
		pendingMoves: initialStateSnapshot.pendingMoves,
		filters: initialStateSnapshot.filters,
		viewOverride: initialStateSnapshot.viewOverride,
		nextRefreshAt: initialStateSnapshot.nextRefreshAt,
		lastFetchedAt: initialStateSnapshot.lastFetchedAt,
	};
	lastModalSnapshot = {
		isOpen: initialStateSnapshot.modal.isOpen,
		activeTab: initialStateSnapshot.modal.activeTab,
		loading: initialStateSnapshot.modal.loading,
		readOnly: initialStateSnapshot.modal.readOnly,
		isSaving: initialStateSnapshot.modal.isSaving,
		error: initialStateSnapshot.modal.error,
		visitId: initialStateSnapshot.modal.visit?.id || null,
		pendingUploads: initialStateSnapshot.modal.pendingUploads,
		isPreparingUploads: initialStateSnapshot.modal.isPreparingUploads,
	};
	lastIntakeSnapshot = initialStateSnapshot.intake;
	lastCatalogRef = initialStateSnapshot.catalog;
	lastErrorsRef = initialStateSnapshot.errors;

	store.subscribe((state) => {
		const boardChanged =
			!lastBoardStateRef ||
			state.board !== lastBoardStateRef.board ||
			state.isLoading !== lastBoardStateRef.isLoading ||
			state.pendingMoves !== lastBoardStateRef.pendingMoves ||
			state.filters !== lastBoardStateRef.filters ||
			state.viewOverride !== lastBoardStateRef.viewOverride ||
			state.nextRefreshAt !== lastBoardStateRef.nextRefreshAt ||
			state.lastFetchedAt !== lastBoardStateRef.lastFetchedAt;

		const errorsChanged = state.errors !== lastErrorsRef;
		if (boardChanged) {
			renderBoard(state, root, settings, strings);
			updateToolbarStates(root, state);
			attachToolbarHandlers(root);
			setupLastUpdatedTicker(root);
			handleModalEvents(root);
			setupDragAndDrop(root, state);
			handleIntakeEvents(root);
			lastBoardStateRef = {
				board: state.board,
				isLoading: state.isLoading,
				pendingMoves: state.pendingMoves,
				filters: state.filters,
				viewOverride: state.viewOverride,
				nextRefreshAt: state.nextRefreshAt,
				lastFetchedAt: state.lastFetchedAt,
			};
		} else if (errorsChanged) {
			renderErrors(state, root, strings);
		}

		const modalStructuralChanged =
			!lastModalSnapshot ||
			state.modal.isOpen !== lastModalSnapshot.isOpen ||
			state.modal.activeTab !== lastModalSnapshot.activeTab ||
			state.modal.loading !== lastModalSnapshot.loading ||
			state.modal.readOnly !== lastModalSnapshot.readOnly ||
			state.modal.isSaving !== lastModalSnapshot.isSaving ||
			state.modal.error !== lastModalSnapshot.error ||
			state.modal.pendingUploads !== lastModalSnapshot.pendingUploads ||
			state.modal.isPreparingUploads !== lastModalSnapshot.isPreparingUploads ||
			(state.modal.visit?.id || null) !== (lastModalSnapshot.visitId || null) ||
			state.catalog !== lastCatalogRef;

		if (modalStructuralChanged) {
			renderModal(state, root, strings);
			handleModalEvents(root);
			lastModalSnapshot = {
				isOpen: state.modal.isOpen,
				activeTab: state.modal.activeTab,
				loading: state.modal.loading,
				readOnly: state.modal.readOnly,
				isSaving: state.modal.isSaving,
				error: state.modal.error,
				visitId: state.modal.visit?.id || null,
				pendingUploads: state.modal.pendingUploads,
				isPreparingUploads: state.modal.isPreparingUploads,
			};
			lastCatalogRef = state.catalog;
		}

		const intakeChanged = state.intake !== lastIntakeSnapshot;
		if (intakeChanged) {
			renderIntakeModal(state, root, strings);
			handleIntakeEvents(root);
			lastIntakeSnapshot = state.intake;
		}

		lastErrorsRef = state.errors;
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
	root.addEventListener('touchstart', handleTouchStart, { passive: true });
	root.addEventListener('touchmove', handleTouchMove, { passive: false });
	root.addEventListener('touchend', handleTouchEnd, { passive: true });

	loadBoard({ reason: 'initial', forceFull: !settings.initialBoard });
});
