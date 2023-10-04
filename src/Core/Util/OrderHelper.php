<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Util;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Tilta\TiltaPaymentSW6\Core\Exception\OrderIsNotATiltaOrder;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderDataEntity;
use Tilta\TiltaPaymentSW6\Core\Extension\OrderDataEntityExtension;

class OrderHelper
{
    private EntityRepository $documentRepository;

    public function __construct(
        EntityRepository $documentRepository
    ) {
        $this->documentRepository = $documentRepository;
    }

    public function getInvoiceNumberAndExternalId(OrderEntity $orderEntity): ?array
    {
        /** @var TiltaOrderDataEntity|null $tiltaData */
        $tiltaData = $orderEntity->getExtension(OrderDataEntityExtension::EXTENSION_NAME);

        if (!$tiltaData instanceof TiltaOrderDataEntity) {
            throw new OrderIsNotATiltaOrder();
        }

        $invoiceNumber = $tiltaData->getInvoiceNumber();
        $invoiceExternalId = $tiltaData->getInvoiceExternalId();

        if ($invoiceNumber === null || $invoiceExternalId === null) {
            $fallback = $this->getInvoiceNumberAndExternalIdFromDocument($orderEntity);
            if ($fallback !== null) {
                $invoiceNumber = $invoiceNumber ?: $fallback[0];
                $invoiceExternalId = $invoiceExternalId ?: $fallback[1];
            }
        }

        return $invoiceNumber === null || $invoiceExternalId === null ? null : [$invoiceNumber, $invoiceExternalId];
    }

    private function getInvoiceNumberAndExternalIdFromDocument(OrderEntity $orderEntity): ?array
    {
        $documents = $orderEntity->getDocuments();
        if (!$documents instanceof DocumentCollection) {
            $documentCriteria = new Criteria();
            $documentCriteria->addAssociation('documentType');
            $documentCriteria->addFilter(new EqualsFilter('orderId', $orderEntity->getId()));
            $documentCriteria->addFilter(new EqualsFilter('document.type', InvoiceRenderer::TYPE));
            $documentCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
            $documents = $this->documentRepository->search($documentCriteria, Context::createDefaultContext());
        }

        /** @var DocumentEntity $document */
        foreach ($documents as $document) {
            if (!$document->getDocumentType() instanceof DocumentTypeEntity) {
                continue;
            }

            if ($document->getDocumentType()->getTechnicalName() === InvoiceRenderer::TYPE) {
                $config = $document->getConfig();
                $invoiceNumber = $config['custom']['invoiceNumber'] ?? null;

                return [
                    $invoiceNumber ?: $document->getId(),
                    $document->getId(),
                ];
            }
        }

        return null;
    }
}