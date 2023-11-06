import template from './sw-order-detail.html.twig';
import deDE from "./snippet/de-DE.json";
import enGB from "./snippet/en-GB.json";

const {Component} = Shopware;

Component.override('sw-order-detail', {
    template,
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },
});
