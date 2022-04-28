<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\CatalogPriceProductConnector\Plugin;

use Elastica\ResultSet;
use Generated\Shared\Transfer\CurrentProductPriceTransfer;
use Spryker\Client\CatalogPriceProductConnector\Dependency\CatalogPriceProductConnectorToPriceProductClientInterface;
use Spryker\Client\CatalogPriceProductConnector\Dependency\CatalogPriceProductConnectorToPriceProductStorageClientInterface;
use Spryker\Client\Search\Dependency\Plugin\ResultFormatterPluginInterface;
use Spryker\Client\Search\Plugin\Elasticsearch\ResultFormatter\AbstractElasticsearchResultFormatterPlugin;

/**
 * @method \Spryker\Client\CatalogPriceProductConnector\CatalogPriceProductConnectorFactory getFactory()
 */
class CurrencyAwareCatalogSearchResultFormatterPlugin extends AbstractElasticsearchResultFormatterPlugin
{
    /**
     * @var array<\Generated\Shared\Transfer\CurrentProductPriceTransfer>
     */
    protected $productPriceTransfersByIdAbstractProduct = [];

    /**
     * @var \Spryker\Client\Search\Dependency\Plugin\ResultFormatterPluginInterface
     */
    protected $rawCatalogSearchResultFormatterPlugin;

    /**
     * @param \Spryker\Client\Search\Dependency\Plugin\ResultFormatterPluginInterface $rawCatalogSearchResultFormatterPlugin
     */
    public function __construct(ResultFormatterPluginInterface $rawCatalogSearchResultFormatterPlugin)
    {
        $this->rawCatalogSearchResultFormatterPlugin = $rawCatalogSearchResultFormatterPlugin;
    }

    /**
     * @param \Elastica\ResultSet $searchResult
     * @param array<string, mixed> $requestParameters
     *
     * @return array
     */
    protected function formatSearchResult(ResultSet $searchResult, array $requestParameters)
    {
        $result = $this->rawCatalogSearchResultFormatterPlugin->formatResult($searchResult, $requestParameters);

        if (!$this->isPriceProductDimensionEnabled()) {
            return $this->formatSearchResultWithoutPriceDimensions($result);
        }

        $priceProductClient = $this->getFactory()->getPriceProductClient();
        $priceProductStorageClient = $this->getFactory()->getPriceProductStorageClient();
        foreach ($result as &$product) {
            $currentProductPriceTransfer = $this->getPriceProductAbstractTransfers($product['id_product_abstract'], $priceProductClient, $priceProductStorageClient);
            $product['price'] = $currentProductPriceTransfer->getPrice();
            $product['prices'] = $currentProductPriceTransfer->getPrices();
        }

        return $result;
    }

    /**
     * @param int $idProductAbstract
     * @param \Spryker\Client\CatalogPriceProductConnector\Dependency\CatalogPriceProductConnectorToPriceProductClientInterface $priceProductClient
     * @param \Spryker\Client\CatalogPriceProductConnector\Dependency\CatalogPriceProductConnectorToPriceProductStorageClientInterface $priceProductStorageClient
     *
     * @return \Generated\Shared\Transfer\CurrentProductPriceTransfer
     */
    protected function getPriceProductAbstractTransfers(
        int $idProductAbstract,
        CatalogPriceProductConnectorToPriceProductClientInterface $priceProductClient,
        CatalogPriceProductConnectorToPriceProductStorageClientInterface $priceProductStorageClient
    ): CurrentProductPriceTransfer {
        if (isset($this->productPriceTransfersByIdAbstractProduct[$idProductAbstract])) {
            return $this->productPriceTransfersByIdAbstractProduct[$idProductAbstract];
        }

        $priceProductTransfersFromStorage = $priceProductStorageClient->getPriceProductAbstractTransfers($idProductAbstract);
        $currentProductPriceTransfer = $priceProductClient->resolveProductPriceTransfer($priceProductTransfersFromStorage);

        $this->productPriceTransfersByIdAbstractProduct[$idProductAbstract] = $currentProductPriceTransfer;

        return $this->productPriceTransfersByIdAbstractProduct[$idProductAbstract];
    }

    /**
     * Fallback method to work with PriceProduct module without price dimensions support.
     *
     * @param array $result
     *
     * @return array
     */
    protected function formatSearchResultWithoutPriceDimensions(array $result)
    {
        $priceProductClient = $this->getFactory()->getPriceProductClient();
        foreach ($result as &$product) {
            $currentProductPriceTransfer = $priceProductClient->resolveProductPrice($product['prices']);
            $product['price'] = $currentProductPriceTransfer->getPrice();
            $product['prices'] = $currentProductPriceTransfer->getPrices();
        }

        return $result;
    }

    /**
     * @return bool
     */
    protected function isPriceProductDimensionEnabled(): bool
    {
        return defined('\Spryker\Shared\PriceProduct\PriceProductConstants::PRICE_DIMENSION_DEFAULT');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->rawCatalogSearchResultFormatterPlugin->getName();
    }
}
