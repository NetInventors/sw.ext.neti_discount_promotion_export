<?php
/**
 * @copyright  Copyright (c) 2017, Net Inventors GmbH
 * @category   NetiDiscountPromotionExport
 * @author     bmueller
 */

namespace NetiDiscountPromotionExport\Service;

use Shopware\Bundle\StoreFrontBundle\Service\AdditionalTextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ConfiguratorServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ProductServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ListProduct;
use Zend_Date;

/**
 * Class Export
 *
 * @package NetiDiscountPromotionExport\Service
 */
class Export implements ExportInterface
{
    /**
     * @var \Shopware_Components_Modules
     */
    protected $swModules;

    /**
     * @var ContextServiceInterface
     */
    protected $contextService;

    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    protected $db;

    /**
     * @var AdditionalTextServiceInterface
     */
    protected $additionalTextService;

    /**
     * @var ConfiguratorServiceInterface
     */
    protected $configuratorService;

    /**
     * @var ProductServiceInterface
     */
    protected $productService;

    /**
     * @var \Enlight_Template_Manager
     */
    protected $sSmarty;

    /**
     * @var array
     */
    protected $sSettings;

    /**
     * @var int
     */
    protected $sFeedID;

    /**
     * Export constructor.
     *
     * @param \Shopware_Components_Modules             $swModules
     * @param ContextServiceInterface                  $contextService
     * @param \Enlight_Components_Db_Adapter_Pdo_Mysql $db
     * @param AdditionalTextServiceInterface           $additionalTextService
     * @param ConfiguratorServiceInterface             $configuratorService
     * @param ProductServiceInterface                  $productService
     */
    public function __construct(
        \Shopware_Components_Modules $swModules,
        ContextServiceInterface $contextService,
        \Enlight_Components_Db_Adapter_Pdo_Mysql $db,
        AdditionalTextServiceInterface $additionalTextService,
        ConfiguratorServiceInterface $configuratorService,
        ProductServiceInterface $productService
    ) {
        $this->swModules             = $swModules;
        $this->contextService        = $contextService;
        $this->db                    = $db;
        $this->additionalTextService = $additionalTextService;
        $this->configuratorService   = $configuratorService;
        $this->productService        = $productService;
    }

    /**
     * executes the current product export
     *
     * @param resource $handleResource used as a file or the stdout to fetch the smarty output
     */
    public function executeExport($handleResource)
    {
        fwrite($handleResource, $this->sSmarty->fetch('string:' . $this->sSettings['header'], $this->sFeedID));

        $context = $this->contextService->getShopContext();

        $sql = $this->sCreateSql();

        $result = $this->db->query($sql);

        if ($result === false) {
            return;
        }

        // Update db with the latest values
        $count = (int) $result->rowCount();
        $this->db->update(
            's_export',
            array(
                'last_export'     => new Zend_Date(),
                'cache_refreshed' => new Zend_Date(),
                'count_articles'  => $count
            ),
            array('id = ?' => $this->sFeedID)
        );

        // fetches all required data to smarty
        $rows = array();
        for ($rowIndex = 1; $row = $result->fetch(); $rowIndex++) {
            if (! empty($row['group_ordernumber_2'])) {
                $row['group_ordernumber'] = $this->_decode_line($row['group_ordernumber_2']);
                $row['group_pricenet']    = explode(';', $row['group_pricenet_2']);
                $row['group_price']       = explode(';', $row['group_price_2']);
                $row['group_instock']     = explode(';', $row['group_instock_2']);
                $row['group_active']      = explode(';', $row['group_active_2']);
                unset($row['group_ordernumber_2'], $row['group_pricenet_2']);
                unset($row['group_price_2'], $row['group_instock_2'], $row['group_active_2']);
                for ($i = 1; $i <= 10; $i++) {
                    if (! empty($row['group_group' . $i])) {
                        $row['group_group' . $i] = $this->_decode_line($row['group_group' . $i]);
                    } else {
                        unset($row['group_group' . $i]);
                    }
                    if (! empty($row['group_option' . $i])) {
                        $row['group_option' . $i] = $this->_decode_line($row['group_option' . $i]);
                    } else {
                        unset($row['group_option' . $i]);
                    }
                }
                unset($row['group_additionaltext']);
            } elseif (! empty($row['group_ordernumber'])) {
                $row['group_ordernumber']    = $this->_decode_line($row['group_ordernumber']);
                $row['group_additionaltext'] = $this->_decode_line($row['group_additionaltext']);
                $row['group_pricenet']       = explode(';', $row['group_pricenet']);
                $row['group_price']          = explode(';', $row['group_price']);
                $row['group_instock']        = explode(';', $row['group_instock']);
                $row['group_active']         = explode(';', $row['group_active']);
            }

            if (! empty($row['article_translation_fallback'])) {
                $translation = $this->sMapTranslation('article', $row['article_translation_fallback']);
                if ($row['main_detail_id'] != $row['articledetailsID']) {
                    unset($translation['additionaltext']);
                }
                $row = array_merge($row, $translation);
            }
            if (! empty($row['article_translation'])) {
                $translation = $this->sMapTranslation('article', $row['article_translation']);
                if ($row['main_detail_id'] != $row['articledetailsID']) {
                    unset($translation['additionaltext']);
                }
                $row = array_merge($row, $translation);
            }

            if (! empty($row['detail_translation_fallback'])) {
                $translation = $this->sMapTranslation('detail', $row['detail_translation_fallback']);
                $row         = array_merge($row, $translation);
            }
            if (! empty($row['detail_translation'])) {
                $translation = $this->sMapTranslation('detail', $row['detail_translation']);
                $row         = array_merge($row, $translation);
            }

            $row['name']     = htmlspecialchars_decode($row['name']);
            $row['supplier'] = htmlspecialchars_decode($row['supplier']);

            //cast it to float to prevent the division by zero warning
            $row['purchaseunit']  = floatval($row['purchaseunit']);
            $row['referenceunit'] = floatval($row['referenceunit']);
            if (! empty($row['purchaseunit']) && ! empty($row['referenceunit'])) {
                $row['referenceprice'] = Shopware()->Modules()->Articles()->calculateReferencePrice(
                    $row['price'],
                    $row['purchaseunit'],
                    $row['referenceunit']
                );
            }
            if ($row['configurator'] > 0) {
                if (empty($this->sSettings["variant_export"]) || $this->sSettings["variant_export"] == 1) {
                    $row['group_additionaltext'] = array();

                    if (! empty($row['group_ordernumber'])) {
                        foreach ($row['group_ordernumber'] as $orderNumber) {
                            $product = new ListProduct(
                                (int) $row['articleID'],
                                (int) $row["articledetailsID"],
                                $orderNumber
                            );

                            $product->setAdditional($row['additionaltext']);

                            $product = $this->additionalTextService->buildAdditionalText($product, $context);

                            if (array_key_exists($orderNumber, $row['group_additionaltext'])) {
                                $row['group_additionaltext'][$orderNumber] = $product->getAdditional();
                            }
                            if ($orderNumber == $row['ordernumber']) {
                                $row['additionaltext'] = $product->getAdditional();
                            }
                        }
                    }
                }
                $product = new ListProduct(
                    (int) $row['articleID'],
                    (int) $row["articledetailsID"],
                    $row['ordernumber']
                );

                $product->setAdditional($row['additionaltext']);

                $product = $this->additionalTextService->buildAdditionalText($product, $context);

                $row['additionaltext']       = $product->getAdditional();
                $row['configurator_options'] = [];

                $configurationGroups = $this->configuratorService->getProductConfiguration($product, $context);

                /**
                 * @var \Shopware\Bundle\StoreFrontBundle\Struct\Configurator\Group $configuratorOption
                 */
                foreach ($configurationGroups as $configurationGroup) {
                    $option                                                      = current($configurationGroup->getOptions());
                    $row['configurator_options'][$configurationGroup->getName()] = $option->getName();
                }
            }
            $rows[] = $row;

            if ($rowIndex == $count || count($rows) >= 50) {
                @set_time_limit(30);

                $rows = Shopware()->Container()->get('events')->filter(
                    'Shopware_Modules_Export_ExportResult_Filter',
                    $rows,
                    ['feedId' => $this->sFeedID, 'subject' => $this]
                );

                $this->sSmarty->assign('sArticles', $rows);
                $rows = array();

                $template = 'string:{foreach $sArticles as $sArticle}' . $this->sSettings['body'] . '{/foreach}';

                fwrite($handleResource, $this->sSmarty->fetch($template, $this->sFeedID));
            }
        }
        fwrite($handleResource, $this->sSmarty->fetch('string:' . $this->sSettings['footer'], $this->sFeedID));
        fclose($handleResource);
    }

    /**
     * @param $object
     * @param $objectData
     *
     * @return array
     */
    public function sMapTranslation($object, $objectData)
    {
        return $this->swModules->Export()->sMapTranslation($object, $objectData);
    }

    /**
     * @return string
     */
    public function sCreateSql()
    {
        return $this->swModules->Export()->sCreateSql();
    }

    /**
     * @param string $line
     *
     * @return array
     */
    public function _decode_line($line)
    {
        return $this->swModules->Export()->_decode_line($line);
    }

    /**
     * Sets the Value to sSettings in the record
     *
     * @param array $sSettings
     *
     * @return self
     */
    public function setSSettings($sSettings)
    {
        $this->sSettings = $sSettings;

        return $this;
    }

    /**
     * Sets the Value to sFeedID in the record
     *
     * @param int $sFeedID
     *
     * @return self
     */
    public function setSFeedID($sFeedID)
    {
        $this->sFeedID = $sFeedID;

        return $this;
    }

    /**
     * Sets the Value to sSmarty in the record
     *
     * @param \Enlight_Template_Manager $sSmarty
     *
     * @return self
     */
    public function setSSmarty($sSmarty)
    {
        $this->sSmarty = $sSmarty;

        return $this;
    }
}
