<?php
/**
 * @copyright  Copyright (c) 2017, Net Inventors GmbH
 * @category   NetiDiscountPromotionExport
 * @author     bmueller
 */

namespace NetiDiscountPromotionExport\Subscriber;

use Enlight\Event\SubscriberInterface;
use NetiDiscountPromotionExport\Service\ExportInterface;

/**
 * Class Export
 *
 * @package NetiDiscountPromotionExport\Subscriber
 */
class Export implements SubscriberInterface
{
    /**
     * @var boolean
     */
    protected $validLicense;

    /**
     * @var ExportInterface
     */
    protected $exportService;

    /**
     * Export constructor.
     *
     * @param ExportInterface $exportService
     */
    public function __construct(
        ExportInterface $exportService
    ) {
        $this->exportService = $exportService;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'sExport::executeExport::replace' => 'onReplaceExecuteExport'
        );
    }

    /**
     * @param \Enlight_Hook_HookArgs $args
     */
    public function onReplaceExecuteExport(\Enlight_Hook_HookArgs $args)
    {
        /** @var \sExport $subject */
        $subject = $args->getSubject();

        $this->exportService->setSFeedID($subject->sFeedID);
        $this->exportService->setSSettings($subject->sSettings);
        $this->exportService->setSSmarty($subject->sSmarty);

        $this->exportService->executeExport(
            $args->get('handleResource')
        );
    }
}
