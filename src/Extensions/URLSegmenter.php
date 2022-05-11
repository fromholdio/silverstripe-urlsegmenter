<?php

namespace Fromholdio\URLSegmenter\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Forms\TextField;

class URLSegmenter extends DataExtension
{
    /**
     * Should URLSegment be forced to match source value
     * @var bool
     */
    private static $urlsegmenter_forced = true;

    /**
     * Sets URLSegmenterFilter to replace or transliterate non-ASCII filters.
     * @var bool
     * @see URLSegmentFilter::$default_allow_multibyte
     */
    private static $urlsegmenter_allow_multibyte = false;

    /**
     * DB field in which to store the resulting URLSegment value.
     * --
     * Defaults to 'URLSegment', and this extension adds that db field.
     * If you change this, you need to ensure you also add the
     * associated db field.
     * @var string
     */
    private static $urlsegmenter_field_name = 'URLSegment';

    /**
     * $db field to get value for initial URLSegment generation
     * Ignored if $urlsegmenter_source_method_name is set.
     * @var string
     */
    private static $urlsegmenter_source_field_name = 'Title';

    /**
     * Method name to get source value for URLSegment generation.
     * - Overrides $urlsegmenter_source_field_name if set.
     * - Expected value is method name without "->" or "()".
     * - Object::hasMethod($methodName) should return true.
     * @var null
     */
    private static $urlsegmenter_source_method_name = null;

    /**
     * Display URLSegment field in CMSFields.
     * --
     * If $urlsegmenter_forced is true, the field will only
     * be displayed after the record is in the database, and will
     * be a ReadOnlyField.
     * @var bool|string
     */
    private static $urlsegmenter_cmsfield_enabled = false;

    /**
     * Provide name of CMS field to be inserted before or after.
     * If null, will be inserted after source_field if exists
     * (else not at all).
     * @var null|string
     */
    private static $urlsegmenter_cmsfield_insertbefore = null;
    private static $urlsegmenter_cmsfield_insertafter = null;


    private static $db = [
        'URLSegment' => 'Varchar(255)'
    ];

    private static $field_labels = [
        'URLSegment' => 'URL Segment'
    ];


    public function isURLSegmenterForced(): bool
    {
        return (bool) $this->getOwner()->config()->get('urlsegmenter_forced');
    }

    public function isURLSegmenterAllowMultibyte(): bool
    {
        return (bool) $this->getOwner()->config()->get('urlsegmenter_allow_multibyte');
    }

    public function getURLSegmenterFieldName(): string
    {
        $name = $this->getOwner()->config()->get('urlsegmenter_field_name');
        if (empty($name)) {
            throw new \UnexpectedValueException(
                'The $urlsegmenter_field_name on ' . get_class($this->getOwner()) . ' is empty.'
            );
        }

        $dbObject = $this->getOwner()->dbObject($name);
        if (!$dbObject) {
            throw new \UnexpectedValueException(
                'The $urlsegmenter_field_name on ' . get_class($this->getOwner())
                . ' does not match a valid dbObject name.'
            );
        }
        return $name;
    }

    public function getURLSegmenterSourceMethodName(): ?string
    {
        $method = $this->getOwner()->config()->get('urlsegmenter_source_method_name');
        $method = empty($method) ? null : $method;
        if (!is_null($method) && !$this->getOwner()->hasMethod($method)) {
            throw new \UnexpectedValueException(
                'The $urlsegmenter_source_method_name on ' . get_class($this->getOwner())
                . ' does not match a valid method name.'
            );
        }
        return $method;
    }

    public function getURLSegmenterSourceFieldName(): ?string
    {
        $name = null;
        $method = $this->getOwner()->getURLSegmenterSourceMethodName();
        if (is_null($method))
        {
            $fieldName = $this->getOwner()->config()->get('urlsegmenter_source_field_name');
            if (empty($fieldName)) {
                throw new \UnexpectedValueException(
                    'The $urlsegmenter_source_field_name on ' . get_class($this->getOwner())
                    . ' is empty (and no $urlsegmenter_source_method_name is set either).'
                );
            }

            $dbObject = $this->getOwner()->dbObject($fieldName);
            if (!$dbObject) {
                throw new \UnexpectedValueException(
                    'The $urlsegmenter_source_field_name "' . $fieldName .'" on ' . get_class($this->getOwner())
                    . ' does not match a valid dbObject name.'
                );
            }
            $name = $fieldName;
        }
        return $name;
    }

    public function getURLSegmenterSourceValue(): string
    {
        $method = $this->getOwner()->getURLSegmenterSourceMethodName();
        if (!is_null($method)) {
            $value = $this->getOwner()->{$method}();
        }
        else {
            $name = $this->getOwner()->getURLSegmenterSourceFieldName();
            $value = $this->getOwner()->{$name};
        }
        if (empty($value)) {
            $value = $this->getOwner()->getURLSegmenterDefaultValue();
        }
        return $value;
    }

    public function getURLSegmenterDefaultValue(): string
    {
        $value = _t(
            'URLSEGMENTER.NEWSOURCEVALUE',
            'New {singularName}',
            ['singularName' => $this->getOwner()->i18n_singular_name()]
        );
        $this->getOwner()->invokeWithExtensions('updateURLSegmenterDefaultValue', $value);
        return $value;
    }


    public function isURLSegmenterCMSFieldEnabled(): bool
    {
        return (bool) $this->getOwner()->config()->get('urlsegmenter_cmsfield_enabled');
    }

    public function getURLSegmenterCMSFieldInsertBefore(): ?string
    {
        $before = $this->getOwner()->config()->get('urlsegmenter_cmsfield_insertbefore');
        return empty($before) ? null : $before;
    }

    public function getURLSegmenterCMSFieldInsertAfter(): ?string
    {
        $before = $this->getOwner()->getURLSegmenterCMSFieldInsertBefore();
        if (!is_null($before)) {
            return null;
        }
        $after = $this->getOwner()->config()->get('urlsegmenter_cmsfield_insertafter');
        return empty($after)
            ? $this->getOwner()->getURLSegmenterSourceFieldName()
            : $after;
    }


    public function getURLSegmenterCMSField(): FormField
    {
        $fieldName = $this->getOwner()->getURLSegmenterFieldName();
        $description = _t('URLSEGMENTER.SPECIALCHARSDESC', 'Special characters are automatically converted or removed.');
        if ($this->getOwner()->isURLSegmenterForced())
        {
            $field = ReadonlyField::create(
                'URLSegment',
                $this->getOwner()->fieldLabel($fieldName),
                $this->getOwner()->{$fieldName}
            );
            $name = $this->getOwner()->getURLSegmenterSourceFieldName();
            if (!is_null($name)) {
                $generated = _t(
                    'URLSEGMENTER.GENERATEDFROMLABEL',
                    'Generated from {fieldLabel}.',
                    ['fieldLabel' => $this->getOwner()->fieldLabel($name)]
                );
                $description = $generated . ' ' . $description;
            }
        }
        else {
            $field = TextField::create(
                'URLSegment',
                $this->getOwner()->fieldLabel($fieldName),
                $this->getOwner()->{$fieldName}
            );
        }
        $field->setDescription($description);
        $this->getOwner()->invokeWithExtensions('updateURLSegmenterCMSField', $field);
        return $field;
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $fieldName = $this->getOwner()->getURLSegmenterFieldName();
        $fields->removeByName($fieldName);

        if (!$this->getOwner()->isURLSegmenterCMSFieldEnabled()) {
            return;
        }

        if (!$this->getOwner()->isInDB() && $this->getOwner()->isURLSegmenterForced()) {
            return;
        }

        $insertBefore = $this->getOwner()->getURLSegmenterCMSFieldInsertBefore();
        if ($insertBefore) {
            $urlSegmentField = $this->getOwner()->getURLSegmenterCMSField();
            $fields->insertBefore($insertBefore, $urlSegmentField);
        }
        else {
            $insertAfter = $this->getOwner()->getURLSegmenterCMSFieldInsertAfter();
            if ($insertAfter) {
                $urlSegmentField = $this->getOwner()->getURLSegmenterCMSField();
                $fields->insertAfter($insertAfter, $urlSegmentField);
            }
        }
    }


    public function onBeforeWrite(): void
    {
        $fieldName = $this->getOwner()->getURLSegmenterFieldName();
        if (
            !$this->getOwner()->isInDB()
            || empty($this->getOwner()->{$fieldName})
            || $this->getOwner()->isURLSegmenterForced()
        ) {
            $this->getOwner()->generateURLSegment();
        }
    }


    public function generateURLSegment($increment = 0): string
    {
        $scope = $this->getOwner()->hasMethod('getURLSegmenterScope')
            ? $this->getOwner()->getURLSegmenterScope()
            : get_class($this->getOwner())::get();

        if (is_a($scope, DataList::class) && $scope->count() > 0) {
            if ($this->getOwner()->isInDB()) {
                $scope = $scope->exclude('ID', $this->getOwner()->ID);
            }
        }

        $increment = (int) $increment;
        $filter = URLSegmentFilter::create();
        $filter->setAllowMultibyte($this->getOwner()->isURLSegmenterAllowMultibyte());

        $fieldName = $this->getOwner()->getURLSegmenterFieldName();

        $sourceValue = $this->getOwner()->getURLSegmenterSourceValue();
        $this->getOwner()->{$fieldName} = $filter->filter($sourceValue);

        if ($increment > 0) {
            $this->getOwner()->{$fieldName} .= '-' . $increment;
        }

        $duplicates = $scope->filter($fieldName, $this->getOwner()->{$fieldName});
        if ($duplicates->count() > 0) {
            $this->getOwner()->generateURLSegment($increment + 1);
        }

        return $this->getOwner()->{$fieldName};
    }
}
