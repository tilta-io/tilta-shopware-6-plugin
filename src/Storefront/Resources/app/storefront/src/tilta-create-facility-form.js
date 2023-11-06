import Plugin from 'src/plugin-system/plugin.class';
import LoadingIndicator from 'src/utility/loading-indicator/loading-indicator.util';

export default class TiltaCreateFacilityForm extends Plugin {
    init() {
        this._registerEvents();
    }

    _registerEvents() {
        this.el.addEventListener('submit', this._submitForm.bind(this));
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
