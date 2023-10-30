import Plugin from 'src/plugin-system/plugin.class';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';

export default class TiltaCreateFacilityForm extends Plugin {
    init() {
        this._registerEvents();
    }

    _registerEvents() {
        if (!('csrf' in window) || window.csrf.mode === 'twig') {
            this.el.addEventListener('submit', this._submitForm.bind(this));
        } else {
            /**
             * @deprecated tag:6.5.0 CSRF will be removed in  6.5.0.0 - we only need to subscribe the `submit`-event.
             */
            this.el.addEventListener('beforeSubmit', this._submitForm.bind(this))
        }
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
}
