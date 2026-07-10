(function () {
	'use strict';

	var POLL_INTERVAL_MS = 500;

	function normalizeValues(value) {
		if (Array.isArray(value)) {
			return value
				.map(function (item) {
					return String(item);
				})
				.filter(function (item) {
					return item !== '';
				});
		}

		if (value === null || value === undefined || value === '') {
			return [];
		}

		return [String(value)];
	}

	function normalizeExpected(expected) {
		return normalizeValues(expected);
	}

	function includesValue(actual, expected) {
		var expectedValues = normalizeExpected(expected);

		return expectedValues.some(function (value) {
			return actual.indexOf(value) !== -1;
		});
	}

	function includesAll(actual, expected) {
		var expectedValues = normalizeExpected(expected);

		return expectedValues.every(function (value) {
			return actual.indexOf(value) !== -1;
		});
	}

	function includesAny(actual, expected) {
		var expectedValues = normalizeExpected(expected);

		if (!expectedValues.length || !actual.length) {
			return false;
		}

		return expectedValues.some(function (value) {
			return actual.indexOf(value) !== -1;
		});
	}

	function equalsSet(actual, expected) {
		var expectedValues = normalizeExpected(expected).slice().sort();
		var actualValues = actual.slice().sort();

		if (actualValues.length !== expectedValues.length) {
			return false;
		}

		return actualValues.every(function (value, index) {
			return value === expectedValues[index];
		});
	}

	function inValue(actual, expected) {
		var expectedValues = normalizeExpected(expected);

		if (!expectedValues.length || !actual.length) {
			return false;
		}

		return actual.some(function (value) {
			return expectedValues.indexOf(value) !== -1;
		});
	}

	function conditionMatches(condition, sourceValues) {
		var field = condition.field || '';
		var operator = condition.operator || 'includes';
		var actual = normalizeValues(sourceValues[field]);

		switch (operator) {
			case 'includes':
				return includesValue(actual, condition.value);
			case 'includesAll':
				return includesAll(actual, condition.value);
			case 'includesAny':
			case 'intersects':
				return includesAny(actual, condition.value);
			case 'equals':
				return equalsSet(actual, condition.value);
			case 'in':
				return inValue(actual, condition.value);
			case 'any':
				return actual.length > 0;
			default:
				return false;
		}
	}

	function ruleMatches(rule, sourceValues) {
		var when = rule.when || {};
		var group;
		var anyMatch;

		if (when.all && when.all.length) {
			for (group = 0; group < when.all.length; group += 1) {
				if (!conditionMatches(when.all[group], sourceValues)) {
					return false;
				}
			}
		}

		if (when.any && when.any.length) {
			anyMatch = false;
			for (group = 0; group < when.any.length; group += 1) {
				if (conditionMatches(when.any[group], sourceValues)) {
					anyMatch = true;
					break;
				}
			}

			if (!anyMatch) {
				return false;
			}
		}

		return true;
	}

	function resolveOptions(block, optionSets) {
		if (block.use && optionSets[block.use]) {
			return optionSets[block.use].slice();
		}

		if (block.options && block.options.length) {
			return block.options.slice();
		}

		return [];
	}

	function mergeOptions(existing, incoming) {
		var merged = {};
		var result = [];
		var index;

		for (index = 0; index < existing.length; index += 1) {
			merged[existing[index].value] = existing[index];
		}

		for (index = 0; index < incoming.length; index += 1) {
			merged[incoming[index].value] = incoming[index];
		}

		Object.keys(merged).forEach(function (key) {
			result.push(merged[key]);
		});

		return result;
	}

	function evaluateRuleset(ruleset, sourceValues) {
		var behavior = ruleset.behavior || {};
		var matchMode = behavior.match || 'first';
		var optionSets = ruleset.optionSets || {};
		var rules = ruleset.rules || [];
		var matched = [];
		var index;
		var options;

		for (index = 0; index < rules.length; index += 1) {
			if (!ruleMatches(rules[index], sourceValues)) {
				continue;
			}

			options = resolveOptions(rules[index], optionSets);

			if (matchMode === 'first') {
				return options;
			}

			matched = mergeOptions(matched, options);
		}

		if (matched.length) {
			return matched;
		}

		if (ruleset.default) {
			return resolveOptions(ruleset.default, optionSets);
		}

		return [];
	}

	function getFormElement(select) {
		return select.closest('form.elementor-form');
	}

	function getFieldInputs(form, fieldId) {
		var selectors = [
			'[name="form_fields[' + fieldId + ']"]',
			'[name="form_fields[' + fieldId + '][]"]'
		];

		return Array.prototype.slice.call(form.querySelectorAll(selectors.join(',')));
	}

	function readFieldValues(inputs) {
		var values = [];

		inputs.forEach(function (input) {
			if (input.type === 'checkbox' || input.type === 'radio') {
				if (input.checked) {
					values.push(String(input.value));
				}
				return;
			}

			if (input.tagName === 'SELECT' && input.multiple) {
				Array.prototype.slice.call(input.selectedOptions).forEach(function (option) {
					if (option.value !== '') {
						values.push(String(option.value));
					}
				});
				return;
			}

			if (input.value !== '') {
				values.push(String(input.value));
			}
		});

		return values;
	}

	function collectSourceValues(form, sources) {
		var sourceValues = {};
		var index;

		for (index = 0; index < sources.length; index += 1) {
			sourceValues[sources[index]] = readFieldValues(getFieldInputs(form, sources[index]));
		}

		return sourceValues;
	}

	function getSelectSources(select) {
		var sources = [];

		try {
			sources = JSON.parse(select.getAttribute('data-eds-sources') || '[]');
		} catch (error) {
			sources = [];
		}

		if (!sources.length) {
			try {
				var ruleset = JSON.parse(select.getAttribute('data-eds-rules') || '{}');
				if (ruleset.sources) {
					sources = ruleset.sources;
				}
			} catch (rulesError) {
				sources = [];
			}
		}

		return sources;
	}

	function createOption(value, label, selected) {
		var option = document.createElement('option');
		option.value = value;
		option.textContent = label;

		if (selected) {
			option.selected = true;
		}

		return option;
	}

	function rebuildSelect(select, options, ruleset) {
		var behavior = ruleset.behavior || {};
		var preserveSelection = behavior.preserveSelection !== false;
		var placeholder = select.getAttribute('data-eds-placeholder') || 'Select an option';
		var noMatchText = select.getAttribute('data-eds-no-match') || 'No options available';
		var previousValue = select.value;
		var nextValue = '';
		var index;

		select.innerHTML = '';

		if (!options.length) {
			select.appendChild(createOption('', noMatchText, true));
			select.disabled = true;
			select.value = '';
			return;
		}

		select.disabled = false;
		select.appendChild(createOption('', placeholder, false));

		for (index = 0; index < options.length; index += 1) {
			select.appendChild(
				createOption(
					String(options[index].value),
					String(options[index].label || options[index].value),
					false
				)
			);
		}

		if (preserveSelection && previousValue) {
			Array.prototype.slice.call(select.options).forEach(function (option) {
				if (option.value === previousValue) {
					nextValue = previousValue;
				}
			});
		}

		select.value = nextValue;
	}

	function updateSelect(select, force) {
		var form = getFormElement(select);

		if (!form) {
			return;
		}

		var ruleset;
		var sources;
		var sourceValues;
		var options;
		var stateKey;

		try {
			ruleset = JSON.parse(select.getAttribute('data-eds-rules') || '{}');
		} catch (error) {
			return;
		}

		if (!ruleset || !ruleset.rules) {
			return;
		}

		sources = getSelectSources(select);
		sourceValues = collectSourceValues(form, sources);
		stateKey = JSON.stringify(sourceValues);

		if (!force && select.dataset.edsState === stateKey) {
			return;
		}

		select.dataset.edsState = stateKey;
		options = evaluateRuleset(ruleset, sourceValues);
		rebuildSelect(select, options, ruleset);
	}

	function applyToForm(form) {
		if (!form) {
			return;
		}

		var selects = form.querySelectorAll('select.eds-dynamic-select[data-eds-rules]');

		Array.prototype.forEach.call(selects, function (select) {
			var isNew = !select.dataset.edsBound;

			if (isNew) {
				select.dataset.edsBound = '1';
			}

			updateSelect(select, isNew);
		});
	}

	function applyToAllForms(root) {
		var scope = root || document;
		var forms;

		if (scope.matches && scope.matches('form.elementor-form')) {
			applyToForm(scope);
			return;
		}

		forms = scope.querySelectorAll('form.elementor-form');

		Array.prototype.forEach.call(forms, applyToForm);
	}

	function onFormInteraction(event) {
		var target = event.target;
		var form;

		if (!target || !target.closest) {
			return;
		}

		form = target.closest('form.elementor-form');

		if (!form) {
			return;
		}

		applyToForm(form);
	}

	function initDynamicSelects(context) {
		applyToAllForms(context || document);
	}

	function boot() {
		applyToAllForms(document);

		document.addEventListener('change', onFormInteraction);
		document.addEventListener('input', onFormInteraction);

		setInterval(function () {
			applyToAllForms(document);
		}, POLL_INTERVAL_MS);

		if (typeof MutationObserver !== 'undefined' && document.body) {
			new MutationObserver(function () {
				applyToAllForms(document);
			}).observe(document.body, {
				childList: true,
				subtree: true
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.EDS = window.EDS || {};
	window.EDS.initDynamicSelects = initDynamicSelects;
	window.EDS.evaluateRuleset = evaluateRuleset;
})();
