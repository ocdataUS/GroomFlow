/* global wp */
(function ( window, document ) {
	'use strict';

	const wpGlobal = window.wp || {};
	const __ = ( text ) => {
		if ( wpGlobal.i18n && typeof wpGlobal.i18n.__ === 'function' ) {
			return wpGlobal.i18n.__( text, 'bb-groomflow' );
		}

		return text;
	};

	const TEXT = {
		choose: __( 'Choose emoji' ),
		change: __( 'Change emoji' ),
		searchLabel: __( 'Search emoji' ),
		searchPlaceholder: __( 'Search emoji…' ),
		empty: __( 'No matching emoji. Try another search term.' ),
		emoji: __( 'Emoji' ),
		stageRemoveConfirm: __( 'Remove this stage from the catalog?' ),
		stageRemoveWarning: __( 'Removing this stage will remove it from any linked views and move active clients back to the previous stage.' ),
		stageRemoveUsage: __( 'Currently used by:' ),
		viewStageRemoveConfirm: __( 'Remove stage from this view?' ),
	};

	let activePicker = null;

	const EMOJI_CHOICES = [
		{ char: '🐶', keywords: [ 'dog', 'pup', 'pet', 'friendly', 'loyal' ] },
		{ char: '🐕', keywords: [ 'dog', 'canine', 'walk', 'leash', 'active' ] },
		{ char: '🐩', keywords: [ 'poodle', 'style', 'show', 'groom' ] },
		{ char: '🐕‍🦺', keywords: [ 'support', 'service', 'working', 'alert' ] },
		{ char: '🦮', keywords: [ 'service', 'working', 'guide', 'harness' ] },
		{ char: '🐈', keywords: [ 'cat', 'feline', 'independent', 'calm' ] },
		{ char: '🐈‍⬛', keywords: [ 'black', 'cat', 'stealth', 'shy' ] },
		{ char: '🐱', keywords: [ 'cat', 'happy', 'kitty', 'friendly' ] },
		{ char: '😺', keywords: [ 'cat', 'smile', 'playful', 'kitty' ] },
		{ char: '🐹', keywords: [ 'hamster', 'small', 'pocket', 'pet' ] },
		{ char: '🐰', keywords: [ 'bunny', 'rabbit', 'gentle', 'calm' ] },
		{ char: '🐻', keywords: [ 'bear', 'big', 'gentle', 'strong' ] },
		{ char: '🐼', keywords: [ 'panda', 'mellow', 'calm', 'relax' ] },
		{ char: '🐨', keywords: [ 'koala', 'sleepy', 'cuddly', 'calm' ] },
		{ char: '🐔', keywords: [ 'chicken', 'cluck', 'barn' ] },
		{ char: '🐣', keywords: [ 'chick', 'timid', 'baby' ] },
		{ char: '🦆', keywords: [ 'duck', 'water', 'friendly' ] },
		{ char: '🦢', keywords: [ 'swan', 'elegant', 'calm' ] },
		{ char: '🦜', keywords: [ 'parrot', 'talkative', 'bright' ] },
		{ char: '🦉', keywords: [ 'owl', 'watch', 'alert' ] },
		{ char: '🦚', keywords: [ 'peacock', 'showy', 'feathers', 'bright' ] },
		{ char: '🦩', keywords: [ 'flamingo', 'balance', 'style' ] },
		{ char: '🦭', keywords: [ 'seal', 'playful', 'swim' ] },
		{ char: '🐬', keywords: [ 'dolphin', 'friendly', 'water' ] },
		{ char: '🐟', keywords: [ 'fish', 'calm', 'tank' ] },
		{ char: '🐴', keywords: [ 'horse', 'equine', 'stable', 'gentle' ] },
		{ char: '🐎', keywords: [ 'horse', 'pony', 'active', 'miniature' ] },
		{ char: '🦄', keywords: [ 'magic', 'sparkle', 'fun', 'unique' ] },
		{ char: '🦙', keywords: [ 'llama', 'alpaca', 'soft', 'wool' ] },
		{ char: '🐐', keywords: [ 'goat', 'curious', 'farm', 'agile' ] },
		{ char: '🐑', keywords: [ 'sheep', 'wool', 'calm', 'gentle' ] },
		{ char: '🐖', keywords: [ 'pig', 'playful', 'farm', 'muddy' ] },
		{ char: '🐄', keywords: [ 'cow', 'calm', 'farm', 'friendly' ] },
		{ char: '🦌', keywords: [ 'deer', 'timid', 'forest', 'watchful' ] },
		{ char: '🦔', keywords: [ 'hedgehog', 'spiky', 'sensitive', 'small' ] },
		{ char: '🦦', keywords: [ 'otter', 'playful', 'water', 'curious' ] },
		{ char: '🦥', keywords: [ 'sloth', 'slow', 'relaxed', 'calm' ] },
		{ char: '🦨', keywords: [ 'skunk', 'odor', 'caution', 'scent' ] },
		{ char: '🦝', keywords: [ 'raccoon', 'clever', 'nocturnal', 'mischief' ] },
		{ char: '🐾', keywords: [ 'paw', 'tracks', 'footprint' ] },
		{ char: '🛁', keywords: [ 'bath', 'wash', 'soak', 'spa' ] },
		{ char: '🧽', keywords: [ 'sponge', 'scrub', 'clean' ] },
		{ char: '🧼', keywords: [ 'soap', 'sanitize', 'wash' ] },
		{ char: '🧴', keywords: [ 'shampoo', 'bottle', 'conditioner' ] },
		{ char: '🪮', keywords: [ 'comb', 'brush', 'detangle' ] },
		{ char: '💈', keywords: [ 'barber', 'pole', 'groom' ] },
		{ char: '✂️', keywords: [ 'scissors', 'clip', 'trim' ] },
		{ char: '🪒', keywords: [ 'razor', 'shave', 'trim' ] },
		{ char: '🧺', keywords: [ 'towel', 'laundry', 'fresh' ] },
		{ char: '🧻', keywords: [ 'towel', 'dry', 'cleanup' ] },
		{ char: '🪣', keywords: [ 'bucket', 'rinse', 'clean' ] },
		{ char: '🧤', keywords: [ 'glove', 'safety', 'handler' ] },
		{ char: '🪥', keywords: [ 'toothbrush', 'dental', 'clean' ] },
		{ char: '🫧', keywords: [ 'bubbles', 'rinse', 'sparkle' ] },
		{ char: '🚿', keywords: [ 'rinse', 'shower', 'clean' ] },
		{ char: '🧖', keywords: [ 'spa', 'relax', 'steam' ] },
		{ char: '🍪', keywords: [ 'cookie', 'treat', 'reward' ] },
		{ char: '🧁', keywords: [ 'cupcake', 'birthday', 'treat' ] },
		{ char: '🍰', keywords: [ 'cake', 'celebration', 'treat' ] },
		{ char: '🍩', keywords: [ 'donut', 'sweet', 'treat' ] },
		{ char: '🍿', keywords: [ 'popcorn', 'lounge', 'snack' ] },
		{ char: '🍬', keywords: [ 'candy', 'small', 'treat' ] },
		{ char: '🍗', keywords: [ 'turkey', 'treat', 'protein' ] },
		{ char: '🍖', keywords: [ 'bone', 'chew', 'treat' ] },
		{ char: '🥕', keywords: [ 'carrot', 'veggie', 'healthy' ] },
		{ char: '😇', keywords: [ 'angel', 'sweet', 'calm' ] },
		{ char: '😊', keywords: [ 'smile', 'friendly', 'happy' ] },
		{ char: '🙂', keywords: [ 'content', 'calm', 'okay' ] },
		{ char: '😉', keywords: [ 'wink', 'playful', 'friendly' ] },
		{ char: '😍', keywords: [ 'love', 'adore', 'favorite' ] },
		{ char: '🥰', keywords: [ 'cuddle', 'sweet', 'loving' ] },
		{ char: '😘', keywords: [ 'kiss', 'sweet', 'friendly' ] },
		{ char: '🤗', keywords: [ 'hug', 'gentle', 'calm' ] },
		{ char: '😴', keywords: [ 'sleepy', 'tired', 'senior' ] },
		{ char: '😌', keywords: [ 'relaxed', 'zen', 'calm' ] },
		{ char: '😎', keywords: [ 'cool', 'relaxed', 'chill' ] },
		{ char: '🤠', keywords: [ 'adventure', 'outdoors', 'fun' ] },
		{ char: '🥳', keywords: [ 'celebrate', 'party', 'happy' ] },
		{ char: '🤩', keywords: [ 'excited', 'sparkle', 'wow' ] },
		{ char: '🤪', keywords: [ 'hyper', 'playful', 'silly' ] },
		{ char: '😬', keywords: [ 'nervous', 'anxious', 'caution' ] },
		{ char: '😱', keywords: [ 'scared', 'fearful', 'anxious' ] },
		{ char: '😡', keywords: [ 'angry', 'aggressive', 'growl' ] },
		{ char: '😭', keywords: [ 'cry', 'upset', 'sad' ] },
		{ char: '🤒', keywords: [ 'fever', 'sick', 'health' ] },
		{ char: '🤧', keywords: [ 'allergy', 'sneeze', 'sensitive' ] },
		{ char: '🤕', keywords: [ 'injury', 'bandage', 'careful' ] },
		{ char: '🚨', keywords: [ 'urgent', 'alert', 'emergency' ] },
		{ char: '⚠️', keywords: [ 'caution', 'alert', 'warning' ] },
		{ char: '🚫', keywords: [ 'stop', 'block', 'hold' ] },
		{ char: '🛑', keywords: [ 'stop', 'halt', 'danger' ] },
		{ char: '✋', keywords: [ 'pause', 'stop', 'wait' ] },
		{ char: '🌟', keywords: [ 'vip', 'star', 'priority' ] },
		{ char: '🏅', keywords: [ 'elite', 'loyal', 'gold' ] },
		{ char: '🎖️', keywords: [ 'veteran', 'honor', 'service' ] },
		{ char: '🔴', keywords: [ 'red', 'critical', 'urgent' ] },
		{ char: '🟠', keywords: [ 'orange', 'watch', 'medium' ] },
		{ char: '🟡', keywords: [ 'yellow', 'caution', 'moderate' ] },
		{ char: '🟢', keywords: [ 'green', 'go', 'clear' ] },
		{ char: '🔵', keywords: [ 'blue', 'info', 'note' ] },
		{ char: '🟣', keywords: [ 'purple', 'loyalty', 'repeat' ] },
		{ char: '⬛', keywords: [ 'black', 'serious', 'intense' ] },
		{ char: '⬜', keywords: [ 'white', 'neutral', 'fresh' ] },
		{ char: '❗', keywords: [ 'important', 'alert', 'exclamation' ] },
		{ char: '‼️', keywords: [ 'double', 'alert', 'urgent' ] },
		{ char: '❓', keywords: [ 'question', 'info', 'unknown' ] },
		{ char: '🎀', keywords: [ 'bow', 'ribbon', 'style' ] },
		{ char: '💖', keywords: [ 'sparkle', 'favorite', 'love' ] },
		{ char: '💝', keywords: [ 'gift', 'loyalty', 'special' ] },
		{ char: '🪄', keywords: [ 'makeover', 'magic', 'glam' ] },
		{ char: '⭐', keywords: [ 'gold', 'rating', 'best' ] },
		{ char: '🏆', keywords: [ 'champion', 'best', 'top' ] },
		{ char: '🛡️', keywords: [ 'protect', 'safe', 'shield' ] },
		{ char: '🧠', keywords: [ 'training', 'smart', 'behavior' ] },
		{ char: '🧳', keywords: [ 'travel', 'boarding', 'stay' ] },
		{ char: '📅', keywords: [ 'schedule', 'repeat', 'regular' ] },
		{ char: '🗓️', keywords: [ 'calendar', 'bookings' ] },
		{ char: '📆', keywords: [ 'planner', 'agenda', 'schedule' ] },
		{ char: '🕒', keywords: [ 'pickup', 'time' ] },
		{ char: '⏱️', keywords: [ 'timer', 'quick' ] },
		{ char: '⌛', keywords: [ 'waiting', 'delay' ] },
		{ char: '📌', keywords: [ 'pin', 'reminder' ] },
		{ char: '📍', keywords: [ 'location', 'dropoff' ] },
		{ char: '📝', keywords: [ 'notes', 'instructions' ] },
		{ char: '📎', keywords: [ 'paperwork', 'attachments' ] },
		{ char: '🗂️', keywords: [ 'records', 'folder' ] },
		{ char: '☀️', keywords: [ 'sunny', 'bright', 'happy' ] },
		{ char: '🌤️', keywords: [ 'mild', 'fair' ] },
		{ char: '🌧️', keywords: [ 'rain', 'anxious' ] },
		{ char: '⛈️', keywords: [ 'storm', 'intense' ] },
		{ char: '🌨️', keywords: [ 'snow', 'winter' ] },
		{ char: '❄️', keywords: [ 'cool', 'calm' ] },
		{ char: '🔥', keywords: [ 'hot', 'intense' ] },
		{ char: '🌈', keywords: [ 'colorful', 'fun' ] },
		{ char: '🚗', keywords: [ 'transport', 'pickup' ] },
		{ char: '🏠', keywords: [ 'home', 'mobile' ] },
		{ char: '🚪', keywords: [ 'door', 'entry' ] },
		{ char: '🧯', keywords: [ 'fire', 'safety' ] },
		{ char: '🩹', keywords: [ 'bandage', 'care' ] },
		{ char: '🎓', keywords: [ 'training', 'graduate' ] },
		{ char: '🎧', keywords: [ 'noise', 'headphones' ] },
		{ char: '🔇', keywords: [ 'quiet', 'sensitive' ] },
		{ char: '🔊', keywords: [ 'loud', 'alert' ] },
		{ char: '📣', keywords: [ 'announce', 'broadcast' ] },
		{ char: '💬', keywords: [ 'note', 'chat' ] },
		{ char: '📞', keywords: [ 'call', 'contact' ] },
		{ char: '📧', keywords: [ 'email', 'notify' ] },
		{ char: '🔒', keywords: [ 'secure', 'privacy' ] },
		{ char: '🍼', keywords: [ 'puppy', 'kitten', 'young', 'baby' ] },
		{ char: '🧸', keywords: [ 'comfort', 'soothe', 'favorite' ] },
		{ char: '🦴', keywords: [ 'bone', 'chew', 'reward' ] },
		{ char: '🦷', keywords: [ 'teeth', 'dental', 'clean' ] },
		{ char: '🧦', keywords: [ 'booties', 'paws', 'protect' ] },
		{ char: '🧣', keywords: [ 'coat', 'warm', 'winter' ] },
		{ char: '🛏️', keywords: [ 'rest', 'boarding', 'calm' ] },
		{ char: '🛋️', keywords: [ 'lounge', 'waiting', 'lobby' ] },
		{ char: '🩺', keywords: [ 'medical', 'check', 'health' ] },
		{ char: '💉', keywords: [ 'vaccine', 'shot', 'medical' ] },
		{ char: '💊', keywords: [ 'meds', 'pill', 'health' ] },
		{ char: '🧪', keywords: [ 'allergy', 'test', 'sensitive' ] },
		{ char: '🧑‍⚕️', keywords: [ 'vet', 'medical', 'staff' ] },
		{ char: '🧑‍🎨', keywords: [ 'stylist', 'groomer', 'creative' ] },
		{ char: '🤲', keywords: [ 'gentle', 'care', 'hands' ] },
		{ char: '🫶', keywords: [ 'care', 'support', 'love' ] },
		{ char: '🤝', keywords: [ 'handoff', 'team', 'coordinate' ] },
		{ char: '🧑‍🤝‍🧑', keywords: [ 'team', 'assist', 'pair' ] },
		{ char: '🛎️', keywords: [ 'checkin', 'front desk', 'service' ] },
		{ char: '🧾', keywords: [ 'receipt', 'billing', 'invoice' ] },
		{ char: '💳', keywords: [ 'payment', 'card', 'checkout' ] },
		{ char: '🪙', keywords: [ 'tip', 'coin', 'charge' ] },
		{ char: '💡', keywords: [ 'idea', 'tip', 'note' ] },
		{ char: '🪴', keywords: [ 'calm', 'lobby', 'spa' ] },
		{ char: '🌿', keywords: [ 'natural', 'spa', 'calm' ] },
		{ char: '🌺', keywords: [ 'flower', 'scent', 'spa' ] },
		{ char: '🌊', keywords: [ 'calm', 'wash', 'water' ] },
		{ char: '💦', keywords: [ 'rinse', 'spray', 'water' ] },
		{ char: '💨', keywords: [ 'dry', 'blower', 'speed' ] },
		{ char: '🌀', keywords: [ 'sensory', 'anxious', 'swirl' ] },
		{ char: '🧹', keywords: [ 'cleanup', 'shed', 'dander' ] },
	];
	function initViewStageSelector() {
		const select = document.getElementById( 'bbgf-stage-select' );
		const addButton = document.getElementById( 'bbgf-stage-add' );
		const table = document.getElementById( 'bbgf-selected-stages' );
		const template = document.getElementById( 'bbgf-stage-row-template' );

		if ( ! select || ! addButton || ! table || ! template ) {
			return;
		}

		const tbody = table.querySelector( 'tbody' );

		const updateEmptyState = () => {
			const emptyRow = tbody.querySelector( '.is-empty' );
			const hasRows = tbody.querySelectorAll( '.bbgf-selected-stage:not(.is-empty)' ).length > 0;

			if ( emptyRow ) {
				emptyRow.style.display = hasRows ? 'none' : '';
			}
		};

		const setOptionDisabled = ( stageId, disabled ) => {
			if ( ! stageId ) {
				return;
			}

			const option = select.querySelector( `option[value=\"${stageId}\"]` );
			if ( option ) {
				option.disabled = disabled;
			}
		};

		const createRowFromOption = ( option ) => {
			const fragment = template.content.cloneNode( true );
			const row = fragment.querySelector( '.bbgf-selected-stage' );
			const labelEl = row.querySelector( '.bbgf-stage-label' );
			const keyEl = row.querySelector( '.bbgf-stage-key code' );
			const descriptionEl = row.querySelector( '[data-field=\"description\"]' );
			const softEl = row.querySelector( '[data-field=\"soft\"]' );
			const hardEl = row.querySelector( '[data-field=\"hard\"]' );
			const greenEl = row.querySelector( '[data-field=\"green\"]' );
			const yellowEl = row.querySelector( '[data-field=\"yellow\"]' );
			const redEl = row.querySelector( '[data-field=\"red\"]' );
			const removeButton = row.querySelector( '.bbgf-stage-remove' );
			const hiddenInput = row.querySelector( 'input[name=\"stages[]\"]' );

			row.dataset.stageId = option.value;
			row.dataset.stageKey = option.dataset.stageKey || '';

			if ( labelEl ) {
				labelEl.textContent = option.dataset.stageLabel || option.textContent.trim();
			}

			if ( keyEl ) {
				keyEl.textContent = option.dataset.stageKey || '';
			}

			if ( descriptionEl ) {
				if ( option.dataset.stageDescription ) {
					descriptionEl.textContent = option.dataset.stageDescription;
					descriptionEl.classList.remove( 'bbgf-muted' );
				} else {
					descriptionEl.textContent = __( 'No notes' );
					descriptionEl.classList.add( 'bbgf-muted' );
				}
			}

			if ( softEl ) {
				softEl.textContent = option.dataset.stageSoft || '0';
			}

			if ( hardEl ) {
				hardEl.textContent = option.dataset.stageHard || '0';
			}

			if ( greenEl ) {
				greenEl.textContent = option.dataset.stageGreen || '0';
			}

			if ( yellowEl ) {
				yellowEl.textContent = option.dataset.stageYellow || '0';
			}

			if ( redEl ) {
				redEl.textContent = option.dataset.stageRed || '0';
			}

			if ( removeButton ) {
				removeButton.setAttribute( 'data-stage-label', option.dataset.stageLabel || option.textContent.trim() );
				removeButton.setAttribute( 'data-stage-key', option.dataset.stageKey || '' );
			}

			if ( hiddenInput ) {
				hiddenInput.value = option.value;
			}

			tbody.appendChild( row );
			setOptionDisabled( option.value, true );
			updateEmptyState();
		};

		const handleAddClick = () => {
			const option = select.options[ select.selectedIndex ];

			if ( ! option || ! option.value || option.disabled ) {
				return;
			}

			createRowFromOption( option );
			select.value = '';
			addButton.setAttribute( 'disabled', 'disabled' );
		};

		const moveRow = ( row, direction ) => {
			if ( direction === 'up' ) {
				const previous = row.previousElementSibling;
				if ( previous && ! previous.classList.contains( 'is-empty' ) ) {
					tbody.insertBefore( row, previous );
				}
			} else if ( direction === 'down' ) {
				const next = row.nextElementSibling;
				if ( next ) {
					tbody.insertBefore( next, row );
				}
			}
		};

		addButton.addEventListener( 'click', handleAddClick );
		addButton.setAttribute( 'disabled', 'disabled' );

		select.addEventListener( 'change', () => {
			if ( select.value && ! select.options[ select.selectedIndex ].disabled ) {
				addButton.removeAttribute( 'disabled' );
			} else {
				addButton.setAttribute( 'disabled', 'disabled' );
			}
		} );

		tbody.addEventListener( 'click', ( event ) => {
			const target = event.target;
			const row = target.closest( '.bbgf-selected-stage' );

			if ( ! row || row.classList.contains( 'is-empty' ) ) {
				return;
			}

			if ( target.classList.contains( 'bbgf-stage-remove' ) ) {
				event.preventDefault();

				const label = target.getAttribute( 'data-stage-label' ) || row.querySelector( '.bbgf-stage-label' ).textContent;
				const confirmMessage = `${TEXT.viewStageRemoveConfirm}\\n"${label}"`;

				if ( window.confirm( confirmMessage ) ) {
					const stageId = row.dataset.stageId;
					row.remove();
					setOptionDisabled( stageId, false );
					updateEmptyState();
					select.dispatchEvent( new window.Event( 'change' ) );
				}

				return;
			}

			if ( target.classList.contains( 'bbgf-stage-move-up' ) ) {
				event.preventDefault();
				moveRow( row, 'up' );
				return;
			}

			if ( target.classList.contains( 'bbgf-stage-move-down' ) ) {
				event.preventDefault();
				moveRow( row, 'down' );
			}
		} );

		updateEmptyState();
	}

	function filterEmojiChoices( query ) {
		const normalized = ( query || '' ).trim().toLowerCase();

		if ( ! normalized ) {
			return EMOJI_CHOICES;
		}

		return EMOJI_CHOICES.filter( function ( item ) {
			if ( item.char.toLowerCase().includes( normalized ) ) {
				return true;
			}

			return item.keywords.some( function ( keyword ) {
				return keyword.toLowerCase().includes( normalized );
			} );
		} );
	}

	function renderEmojiChoices( state, query ) {
		const results = filterEmojiChoices( query );
		const fragment = document.createDocumentFragment();

		state.grid.textContent = '';

		if ( results.length === 0 ) {
			const empty = document.createElement( 'p' );
			empty.className = 'bbgf-emoji-empty';
			empty.textContent = TEXT.empty;
			state.grid.appendChild( empty );
			return;
		}

		results.forEach( function ( item ) {
			const option = document.createElement( 'button' );

			option.type = 'button';
			option.className = 'bbgf-emoji-grid__item';
			option.textContent = item.char;
			option.setAttribute( 'aria-label', item.keywords[0] || TEXT.emoji );
			option.setAttribute( 'role', 'listitem' );
			option.title = item.keywords[0] || item.char;

			if ( ( state.input.value || '' ).trim() === item.char ) {
				option.classList.add( 'is-selected' );
				option.setAttribute( 'aria-current', 'true' );
			}

			option.addEventListener( 'click', function () {
				state.input.value = item.char;
				state.input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				state.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				updateButtonLabel( state );
				closePicker( state );
				state.input.focus();
			} );

			fragment.appendChild( option );
		} );

		state.grid.appendChild( fragment );
	}

	function updateButtonLabel( state ) {
		const value = ( state.input.value || '' ).trim();

		if ( value ) {
			state.button.classList.add( 'bbgf-emoji-button--has-value' );
			state.button.dataset.bbgfEmojiValue = value;
			state.button.textContent = TEXT.change;
			state.button.setAttribute( 'aria-label', TEXT.change + ' ' + value );
			state.button.title = TEXT.change + ' ' + value;
			state.popover.setAttribute( 'aria-label', TEXT.change );
		} else {
			state.button.classList.remove( 'bbgf-emoji-button--has-value' );
			delete state.button.dataset.bbgfEmojiValue;
			state.button.textContent = TEXT.choose;
			state.button.setAttribute( 'aria-label', TEXT.choose );
			state.button.removeAttribute( 'title' );
			state.popover.setAttribute( 'aria-label', TEXT.choose );
		}
	}

	function positionPopover( state ) {
		if ( ! state.controls ) {
			return;
		}

		const controlsRect = state.controls.getBoundingClientRect();
		const buttonRect = state.button.getBoundingClientRect();

		// Reset so measurements are accurate.
		state.popover.style.left = '0px';
		state.popover.style.top = '0px';

		const popoverRect = state.popover.getBoundingClientRect();

		let left = buttonRect.left - controlsRect.left;
		const overflowRight = left + popoverRect.width - controlsRect.width;

		if ( overflowRight > 0 ) {
			left = Math.max( 0, left - overflowRight );
		}

		const top = state.button.offsetTop + state.button.offsetHeight + 6;

		state.popover.style.left = left + 'px';
		state.popover.style.top = top + 'px';
	}

	function closePicker( state ) {
		if ( ! state.isOpen ) {
			return;
		}

		state.isOpen = false;

		if ( activePicker === state ) {
			activePicker = null;
		}

		state.popover.hidden = true;
		state.button.setAttribute( 'aria-expanded', 'false' );

		document.removeEventListener( 'mousedown', state.handleDocumentClick );
		document.removeEventListener( 'touchstart', state.handleDocumentClick );
		document.removeEventListener( 'keydown', state.handleKeydown );
		window.removeEventListener( 'resize', state.handleScroll );
		window.removeEventListener( 'scroll', state.handleScroll, true );
	}

	function openPicker( state ) {
		if ( state.isOpen ) {
			return;
		}

		if ( activePicker && activePicker !== state ) {
			closePicker( activePicker );
		}

		state.isOpen = true;
		activePicker = state;

		state.popover.hidden = false;
		state.button.setAttribute( 'aria-expanded', 'true' );
		state.search.value = '';
		renderEmojiChoices( state, '' );
		positionPopover( state );

		document.addEventListener( 'mousedown', state.handleDocumentClick );
		document.addEventListener( 'touchstart', state.handleDocumentClick );
		document.addEventListener( 'keydown', state.handleKeydown );
		window.addEventListener( 'resize', state.handleScroll );
		window.addEventListener( 'scroll', state.handleScroll, true );

		window.requestAnimationFrame( function () {
			state.search.focus( { preventScroll: true } );
		} );
	}

	function setupEmojiPicker( wrapper ) {
		if ( wrapper.dataset.bbgfEmojiReady === '1' ) {
			return;
		}

		const input = wrapper.querySelector( '[data-bbgf-emoji-input]' );
		const mount = wrapper.querySelector( '[data-bbgf-emoji-mount]' );

		if ( ! input || ! mount ) {
			return;
		}

		const controls = mount.closest( '.bbgf-emoji-field__controls' ) || wrapper;

		wrapper.dataset.bbgfEmojiReady = '1';

		const state = {
			wrapper: wrapper,
			input: input,
			mount: mount,
			controls: controls,
			button: document.createElement( 'button' ),
			popover: document.createElement( 'div' ),
			search: document.createElement( 'input' ),
			grid: document.createElement( 'div' ),
			isOpen: false,
		};

		state.handleDocumentClick = function ( event ) {
			if ( ! state.isOpen ) {
				return;
			}

			if ( event.target === state.button || state.button.contains( event.target ) ) {
				return;
			}

			if ( state.popover.contains( event.target ) ) {
				return;
			}

			closePicker( state );
		};

		state.handleKeydown = function ( event ) {
			if ( ! state.isOpen ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closePicker( state );
				state.button.focus();
			}
		};

		state.handleScroll = function () {
			if ( ! state.isOpen ) {
				return;
			}

			positionPopover( state );
		};

		if ( controls && controls.classList ) {
			controls.classList.add( 'bbgf-emoji-field__controls--has-picker' );
		}

		mount.classList.add( 'bbgf-emoji-mount' );

		state.button.type = 'button';
		state.button.className = 'button button-secondary bbgf-emoji-button';
		state.button.setAttribute( 'aria-haspopup', 'dialog' );
		state.button.setAttribute( 'aria-expanded', 'false' );
		state.button.addEventListener( 'click', function () {
			if ( state.isOpen ) {
				closePicker( state );
			} else {
				openPicker( state );
			}
		} );

		state.popover.className = 'bbgf-emoji-popover';
		state.popover.setAttribute( 'role', 'dialog' );
		state.popover.setAttribute( 'aria-modal', 'false' );
		state.popover.hidden = true;

		const picker = document.createElement( 'div' );
		picker.className = 'bbgf-emoji-picker';
		state.popover.appendChild( picker );

		const searchId = 'bbgf-emoji-search-' + Math.random().toString( 36 ).slice( 2 );
		const label = document.createElement( 'label' );
		label.className = 'screen-reader-text';
		label.setAttribute( 'for', searchId );
		label.textContent = TEXT.searchLabel;
		picker.appendChild( label );

		state.search.type = 'search';
		state.search.id = searchId;
		state.search.className = 'bbgf-emoji-search';
		state.search.placeholder = TEXT.searchPlaceholder;
		state.search.setAttribute( 'autocomplete', 'off' );
		picker.appendChild( state.search );

		state.grid.className = 'bbgf-emoji-grid';
		state.grid.setAttribute( 'role', 'list' );
		picker.appendChild( state.grid );

		state.search.addEventListener( 'input', function ( event ) {
			renderEmojiChoices( state, event.target.value );
		} );

		mount.textContent = '';
		mount.appendChild( state.button );
		mount.appendChild( state.popover );

		state.input.addEventListener( 'input', function () {
			updateButtonLabel( state );
		} );
		state.input.addEventListener( 'change', function () {
			updateButtonLabel( state );
		} );

		updateButtonLabel( state );
	}

	function mountEmojiFields() {
		document.querySelectorAll( '[data-bbgf-emoji-field]' ).forEach( function ( wrapper ) {
			setupEmojiPicker( wrapper );
		} );
	}

	function initEmojiFields() {
		mountEmojiFields();
	}

	function initPackageServiceSelection() {
		document.querySelectorAll( '.bbgf-package-form' ).forEach( function ( form ) {
			const hidden = form.querySelector( '[data-bbgf-services-selection]' );
			if ( ! hidden ) {
				return;
			}

			const checkboxes = Array.prototype.slice.call(
				form.querySelectorAll( 'input[name="services[]"]' )
			);

			if ( checkboxes.length === 0 ) {
				hidden.value = '';
				return;
			}

			const updateHidden = function () {
				const selected = [];

				checkboxes.forEach( function ( checkbox ) {
					if ( checkbox.checked && checkbox.value ) {
						selected.push( checkbox.value );
					}
				} );

				hidden.value = selected.join( ',' );
			};

			form.addEventListener( 'change', function ( event ) {
				if ( event.target && 'INPUT' === event.target.tagName && 'services[]' === event.target.name ) {
					updateHidden();
				}
			} );

			form.addEventListener( 'submit', function () {
				updateHidden();
			} );

			updateHidden();
		} );
	}

	function initNotificationTriggerRecipients() {
		const optionsGroup = document.querySelector( '[data-bbgf-recipient-options]' );
		const customField = document.querySelector( '[data-bbgf-recipient-custom]' );

		if ( ! optionsGroup || ! customField ) {
			return;
		}

		const toggleCustomField = () => {
			const checked = optionsGroup.querySelector( 'input[name="recipient_type"]:checked' );
			const requiresCustom = checked && '1' === checked.getAttribute( 'data-requires-custom' );
			const textarea = customField.querySelector( 'textarea[name="recipient_email"]' );

			if ( requiresCustom ) {
				customField.hidden = false;
				if ( textarea ) {
					textarea.disabled = false;
				}
			} else {
				customField.hidden = true;
				if ( textarea ) {
					textarea.disabled = true;
				}
			}
		};

		optionsGroup.querySelectorAll( 'input[name="recipient_type"]' ).forEach( ( radio ) => {
			radio.addEventListener( 'change', toggleCustomField );
		} );

		toggleCustomField();
	}

	function initMergeTagHelper() {
		const list = document.querySelector( '[data-bbgf-merge-tags]' );
		const feedback = document.querySelector( '[data-bbgf-merge-tags-feedback]' );

		if ( ! list ) {
			return;
		}

		const hideFeedback = () => {
			if ( feedback ) {
				feedback.hidden = true;
			}
		};

		const showFeedback = () => {
			if ( feedback ) {
				feedback.hidden = false;
				window.clearTimeout( feedback.dataset.timeoutId );
				const timeoutId = window.setTimeout( hideFeedback, 2000 );
				feedback.dataset.timeoutId = timeoutId;
			}
		};

		list.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-bbgf-merge-tag]' );
			if ( ! button ) {
				return;
			}

			const tag = button.getAttribute( 'data-bbgf-merge-tag' );
			if ( ! tag ) {
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( tag ).then( showFeedback ).catch( hideFeedback );
				return;
			}

			const temp = document.createElement( 'textarea' );
			temp.value = tag;
			document.body.appendChild( temp );
			temp.select();

			try {
				document.execCommand( 'copy' );
				showFeedback();
			} catch ( error ) {
				hideFeedback();
			}

			document.body.removeChild( temp );
		} );
	}

function initColorPickers() {
	if ( ! window.jQuery || ! window.jQuery.fn.wpColorPicker ) {
		return;
	}

	window.jQuery( '.bbgf-color-picker' ).each( function () {
		const $input = window.jQuery( this );
		if ( $input.data( 'bbgfColorInit' ) ) {
			return;
		}

		$input.data( 'bbgfColorInit', true );
		$input.wpColorPicker();
	} );
}

	function initStagesAdminPage() {
		const adminWrap = document.querySelector( '.bbgf-stages-admin' );
		if ( ! adminWrap ) {
			return;
		}

		const form = adminWrap.querySelector( '.bbgf-stages-form' );
		const table = adminWrap.querySelector( '.bbgf-stages-table' );
		const tbody = table ? table.querySelector( 'tbody' ) : null;
		const saveButton = form ? form.querySelector( '.bbgf-stages-save' ) : null;
		const actionInput = form ? form.querySelector( 'input[name="bbgf_stage_action"]' ) : null;

		const updateOrderInputs = () => {
			if ( ! tbody ) {
				return;
			}

			const rows = Array.from( tbody.querySelectorAll( '.bbgf-stage-row' ) );
			rows.forEach( ( row, index ) => {
				const display = row.querySelector( '.bbgf-order-display' );
				const orderInput = row.querySelector( '.bbgf-order-input' );
				const orderValue = index + 1;

				if ( display ) {
					display.textContent = orderValue;
				}

				if ( orderInput ) {
					orderInput.value = orderValue;
				}
			} );
		};

		const initDragHandles = () => {
			if ( ! tbody ) {
				return;
			}

			let draggingRow = null;

			tbody.querySelectorAll( '.bbgf-drag-handle' ).forEach( ( handle ) => {
				handle.setAttribute( 'draggable', 'true' );

				handle.addEventListener( 'dragstart', ( event ) => {
					const row = handle.closest( '.bbgf-stage-row' );
					if ( ! row ) {
						event.preventDefault();
						return;
					}

					draggingRow = row;
					row.classList.add( 'is-dragging' );
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData( 'text/plain', '' );
				} );

				handle.addEventListener( 'dragend', () => {
					if ( draggingRow ) {
						draggingRow.classList.remove( 'is-dragging' );
					}
					draggingRow = null;
					updateOrderInputs();
				} );
			} );

			tbody.addEventListener( 'dragover', ( event ) => {
				if ( ! draggingRow ) {
					return;
				}

				event.preventDefault();
				const targetRow = event.target.closest( '.bbgf-stage-row' );
				if ( ! targetRow ) {
					tbody.appendChild( draggingRow );
					return;
				}

				if ( targetRow === draggingRow ) {
					return;
				}

				const rect = targetRow.getBoundingClientRect();
				const offset = event.clientY - rect.top;

				if ( offset > rect.height / 2 ) {
					targetRow.after( draggingRow );
				} else {
					targetRow.before( draggingRow );
				}
			} );

			tbody.addEventListener( 'drop', ( event ) => {
				if ( draggingRow ) {
					event.preventDefault();
					updateOrderInputs();
				}
			} );
		};

		adminWrap.addEventListener( 'click', ( event ) => {
			const target = event.target;
			if ( target.classList.contains( 'bbgf-stage-delete' ) ) {
				if ( actionInput ) {
					actionInput.value = '';
				}

				const usage = target.getAttribute( 'data-stage-usage' ) || '';
				const stageLabel = target.getAttribute( 'data-stage-label' ) || '';
				let messageHeading = TEXT.stageRemoveConfirm;
				if ( stageLabel ) {
					messageHeading = `${TEXT.stageRemoveConfirm}\n"${ stageLabel }"`;
				}
				let messageBody = TEXT.stageRemoveWarning;
				if ( usage ) {
					messageBody = `${messageBody}\n\n${TEXT.stageRemoveUsage}\n${usage}`;
				}
				const prompt = `${messageHeading}\n\n${messageBody}`;

				if ( ! window.confirm( prompt ) ) {
					event.preventDefault();
				}
			}
		}, true );

		if ( form && actionInput ) {
			form.addEventListener( 'submit', ( event ) => {
				const submitter = event.submitter;

				if ( submitter && submitter.classList.contains( 'bbgf-stage-delete' ) ) {
					actionInput.value = '';
					return;
				}

				updateOrderInputs();

				if ( ! actionInput.value ) {
					actionInput.value = 'bulk-save';
				}
			} );
		}

		if ( saveButton && actionInput ) {
			saveButton.addEventListener( 'click', () => {
				updateOrderInputs();
				actionInput.value = 'bulk-save';
			} );
		}

		updateOrderInputs();
		initDragHandles();
	}

	function onReady() {
		initViewStageSelector();
		initStagesAdminPage();
		initEmojiFields();
		initPackageServiceSelection();
		initNotificationTriggerRecipients();
		initMergeTagHelper();
		initColorPickers();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', onReady );
	} else {
		onReady();
	}
})( window, document );
