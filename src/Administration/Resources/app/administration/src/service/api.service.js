const ShopwareApiService = Shopware.Classes.ApiService;

/**
 * @class
 */
export default class ApiService extends ShopwareApiService {
    constructor(httpClient, loginService, apiEndpoint = 'tilta') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'tiltaApiService';
    }

    testCredentials(merchantExternalId, authToken, isSandbox) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/test-api-credentials`,
                {
                    merchantExternalId: merchantExternalId,
                    authToken: authToken,
                    isSandbox: isSandbox
                },
                {
                    headers: this.getBasicHeaders()
                }
            ).then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Shopware.Application.addServiceProvider('tiltaApiService', () => {
    const factoryContainer = Shopware.Application.getContainer('factory');
    const initContainer = Shopware.Application.getContainer('init');

    const apiServiceFactory = factoryContainer.apiService;
    const service = new ApiService(initContainer.httpClient, Shopware.Service('loginService'));
    const serviceName = service.name;
    apiServiceFactory.register(serviceName, service);

    return service;
});
