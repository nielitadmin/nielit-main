(function () {
    'use strict';

    function boot() {
        return window.__STATE_CITY_BOOT__ || { states: [], cities: {}, savedState: '', savedCity: '', savedPincode: '' };
    }

    function updateStateStatus(message, isError, isSuccess) {
        var el = document.getElementById('stateStatus');
        if (!el) return;
        var icon = 'fas fa-spinner fa-spin';
        var color = '#64748b';
        if (isError) {
            icon = 'fas fa-exclamation-triangle';
            color = '#ef4444';
        } else if (isSuccess) {
            icon = 'fas fa-check-circle';
            color = '#10b981';
        }
        el.innerHTML = '<i class="' + icon + '" style="color:' + color + ';"></i> ' + message;
    }

    function updateCityStatus(message, isError, isSuccess) {
        var el = document.getElementById('cityStatus');
        if (!el) return;
        var icon = 'fas fa-spinner fa-spin';
        var color = '#64748b';
        if (isError) {
            icon = 'fas fa-exclamation-triangle';
            color = '#ef4444';
        } else if (isSuccess) {
            icon = 'fas fa-check-circle';
            color = '#10b981';
        }
        el.innerHTML = '<i class="' + icon + '" style="color:' + color + ';"></i> ' + message;
    }

    function loadCities(stateIso2, stateName, citySelect) {
        var cfg = boot();
        updateCityStatus('Loading cities for ' + stateName + '...');
        citySelect.innerHTML = '<option value="">Select city / district</option>';
        citySelect.disabled = true;

        try {
            var cities = (cfg.cities && cfg.cities[stateIso2]) ? cfg.cities[stateIso2] : [];
            if (!Array.isArray(cities) || cities.length === 0) {
                throw new Error('No cities found for this state');
            }
            cities.forEach(function (city) {
                var option = document.createElement('option');
                option.value = city.name;
                option.textContent = city.name;
                option.setAttribute('data-id', city.id || '');
                citySelect.appendChild(option);
            });
            var manualOption = document.createElement('option');
            manualOption.value = 'manual_input';
            manualOption.textContent = 'Type city / district manually';
            manualOption.style.fontStyle = 'italic';
            manualOption.style.color = '#6c757d';
            citySelect.appendChild(manualOption);
            citySelect.disabled = false;
            updateCityStatus(cities.length + ' cities loaded for ' + stateName, false, true);
        } catch (err) {
            var fallback = document.createElement('option');
            fallback.value = 'manual_input';
            fallback.textContent = 'Type city / district manually';
            citySelect.appendChild(fallback);
            citySelect.disabled = false;
            updateCityStatus('Enter city / district manually', true);
        }
    }

    function loadStates(stateSelect, citySelect) {
        var cfg = boot();
        updateStateStatus('Loading Indian states...');
        stateSelect.innerHTML = '<option value="">Select state</option>';
        stateSelect.disabled = true;

        var states = Array.isArray(cfg.states) ? cfg.states : [];
        if (states.length === 0) {
            updateStateStatus('State list unavailable — refresh the page', true);
            return;
        }

        states.forEach(function (state) {
            var option = document.createElement('option');
            option.value = state.iso2;
            option.textContent = state.name;
            option.setAttribute('data-name', state.name);
            option.setAttribute('data-id', state.id || '');
            stateSelect.appendChild(option);
        });
        stateSelect.disabled = false;
        updateStateStatus(states.length + ' states loaded', false, true);

        if (cfg.savedState) {
            stateSelect.value = cfg.savedState;
            var stateName = stateSelect.options[stateSelect.selectedIndex]
                ? stateSelect.options[stateSelect.selectedIndex].getAttribute('data-name') || cfg.savedState
                : cfg.savedState;
            loadCities(cfg.savedState, stateName, citySelect);
            if (cfg.savedCity) {
                setTimeout(function () {
                    citySelect.value = cfg.savedCity;
                    if (citySelect.value !== cfg.savedCity) {
                        var custom = document.createElement('option');
                        custom.value = cfg.savedCity;
                        custom.textContent = cfg.savedCity + ' (saved)';
                        custom.selected = true;
                        citySelect.insertBefore(custom, citySelect.firstChild.nextSibling);
                    }
                    citySelect.disabled = false;
                }, 100);
            }
        }
    }

    function initStateCitySelect() {
        var stateSelect = document.getElementById('state');
        var citySelect = document.getElementById('city');
        if (!stateSelect || !citySelect) {
            return;
        }

        loadStates(stateSelect, citySelect);

        stateSelect.addEventListener('change', function () {
            var stateIso2 = this.value;
            var stateName = this.options[this.selectedIndex]
                ? this.options[this.selectedIndex].getAttribute('data-name') || 'Selected state'
                : 'Selected state';
            citySelect.innerHTML = '<option value="">Select city / district</option>';
            citySelect.disabled = true;
            updateCityStatus('Please select a state first');
            if (stateIso2) {
                loadCities(stateIso2, stateName, citySelect);
            }
        });

        citySelect.addEventListener('change', function () {
            if (this.value !== 'manual_input') {
                return;
            }
            var manualCity = window.prompt('Enter your city / district name:');
            if (manualCity && manualCity.trim()) {
                var option = document.createElement('option');
                option.value = manualCity.trim();
                option.textContent = manualCity.trim() + ' (manual)';
                option.selected = true;
                var manualOpt = this.querySelector('option[value="manual_input"]');
                if (manualOpt) {
                    this.insertBefore(option, manualOpt);
                } else {
                    this.appendChild(option);
                }
                updateCityStatus('City: ' + manualCity.trim(), false, true);
            } else {
                this.value = '';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStateCitySelect);
    } else {
        initStateCitySelect();
    }
})();
