import Plugin from 'src/plugin-system/plugin.class';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';

export default class TiltaCreateFacilityForm extends Plugin {
    init() {
        this.legalFormElement = this.el.querySelector('[name="legalForm"]');
        this.incorporatedAtWrapper = document.getElementById('tilta-incorporated-at-wrapper');
        this.salutationElement = this.el.querySelector('[name="salutationId"]');
        this.salutationWrapper = document.getElementById('tilta-salutation-wrapper');
        this._registerEvents();
    }

    _registerEvents() {
        this.el.addEventListener('submit', this._submitForm.bind(this));

        this.legalFormElement.addEventListener('change', this._onChangeLegalForm.bind(this));
        this._onChangeLegalForm();
    }

    _submitForm(event) {
        if (!event.returnValue) {
            return event.returnValue;
        }

        this.el.classList.add('is-loading');

        const loadingScreen = this.el.querySelector('.tcf_loading-screen');
        if (loadingScreen) {
            const loadingScreenInner = loadingScreen.querySelector('.inner');
            const spinner = document.createElement('div');
            spinner.classList.add('spinner');
            spinner.innerHTML = LoadingIndicator.getTemplate();
            loadingScreenInner.append(spinner)
        }
    }

    _onChangeLegalForm() {
        if (this.legalFormElement.value === 'SOLE_TRADER') {
            this.incorporatedAtWrapper.style.display = '';
            this.incorporatedAtWrapper.querySelectorAll('select').forEach(e => e.required = true);
            this.salutationWrapper.style.display = '';
            this.salutationElement.required = true;
            this.salutationElement.setAttribute('aria-required', true);
        } else {
            this.incorporatedAtWrapper.style.display = 'none';
            this.incorporatedAtWrapper.querySelectorAll('select').forEach(e => e.required = false);
            this.salutationWrapper.style.display = 'none';
            this.salutationElement.required = false;
            this.salutationElement.setAttribute('aria-required', false);
        }
    }
}
