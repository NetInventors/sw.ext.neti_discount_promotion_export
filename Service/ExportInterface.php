<?php
/**
 * @copyright  Copyright (c) 2017, Net Inventors GmbH
 * @category   NetiDiscountPromotionExport
 * @author     bmueller
 */

namespace NetiDiscountPromotionExport\Service;

/**
 * Interface ExportInterface
 *
 * @package NetiDiscountPromotionExport\Service
 */
interface ExportInterface
{
    /**
     * executes the current product export
     *
     * @param resource $handleResource used as a file or the stdout to fetch the smarty output
     */
    public function executeExport($handleResource);

    /**
     * @param $object
     * @param $objectData
     *
     * @return array
     */
    public function sMapTranslation($object, $objectData);

    /**
     * @return string
     */
    public function sCreateSql();

    /**
     * @param string $line
     *
     * @return array
     */
    public function _decode_line($line);

    /**
     * Sets the Value to sSettings in the record
     *
     * @param array $sSettings
     *
     * @return self
     */
    public function setSSettings($sSettings);

    /**
     * Sets the Value to sFeedID in the record
     *
     * @param int $sFeedID
     *
     * @return self
     */
    public function setSFeedID($sFeedID);

    /**
     * Sets the Value to sSmarty in the record
     *
     * @param \Enlight_Template_Manager $sSmarty
     *
     * @return self
     */
    public function setSSmarty($sSmarty);
}
