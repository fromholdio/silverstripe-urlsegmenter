<?php

namespace Fromholdio\URLSegmenter\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Parsers\URLSegmentFilter;

class URLSegmenter extends DataExtension
{
    private static $urlsegmenter_force_title = true;
    private static $urlsegmenter_enable_field = false;

    private static $db = [
        'URLSegment' => 'Varchar(255)'
    ];

    private static $field_labels = [
        'URLSegment' => 'URL Segment'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('URLSegment');

        $enableField = $this->getOwner()->config()->get('urlsegmenter_enable_field');
        if (!$enableField) {
            return;
        }

        if ($this->getOwner()->URLSegment) {
            $urlSegmentField = ReadonlyField::create(
                'URLSegment',
                'URL Segment',
                $this->getOwner()->URLSegment
            );
            $urlSegmentField->setDescription('Based on Title. Special characters are automatically converted or removed.');
            $fields->insertAfter('Title', $urlSegmentField);
        }
    }

    public function onBeforeWrite()
    {
        if ($this->getOwner()->URLSegment) {
            $forceTitle = $this->getOwner()->config()->get('urlsegmenter_force_title');
            if ($forceTitle && $this->getOwner()->isChanged('Title')) {
                $this->getOwner()->generateURLSegment();
            }
        }
        else {
            $this->getOwner()->generateURLSegment();
        }
    }

    public function generateURLSegment($increment = 0)
    {
        if ($this->getOwner()->hasMethod('getURLSegmenterScope')) {

            $increment = (int) $increment;
            $filter = URLSegmentFilter::create();
            $filter->setAllowMultibyte(true);

            $this->getOwner()->URLSegment = $filter->filter($this->getOwner()->Title);
            if (!$this->getOwner()->URLSegment) {
                $this->getOwner()->URLSegment = $filter->filter(
                    'New ' . $this->getOwner()->i18n_singular_name()
                );
            }

            if ($increment > 0) {
                $this->getOwner()->URLSegment .= '-' . $increment;
            }

            $scope = $this->getOwner()->getURLSegmenterScope();
            $duplicates = $scope->filter('URLSegment', $this->getOwner()->URLSegment);

            if ($duplicates->count() > 0) {
                $this->getOwner()->generateURLSegment($increment + 1);
            }

            return $this->getOwner()->URLSegment;
        }
    }
}
