import TiltaCreateFacilityForm from './tilta-create-facility-form';

const PluginManager = window.PluginManager;
let pluginList = PluginManager.getPluginList();

if (!('TiltaCreateFacilityForm' in pluginList)) {
    PluginManager.register('TiltaCreateFacilityForm', TiltaCreateFacilityForm, '[data-tilta-create-facility-form="true"]');
}
