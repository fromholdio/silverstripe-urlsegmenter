# silverstripe-urlsegmenter

Adds an extension to add a URLSegment to any SilverStripe DataObject and manages slug collisions.

That is, it does the basic lifting of:
* Adding a `URLSegment` field of type `DBVarchar` to your object
* Provides an optional update to object's CMS Fields, inserting a URLSegment field (enable this via config yml)
* Auto-generates URLSegment based on object Title field

## Requirements

SilverStripe 4 or 5

## Installation

`composer require fromholdio/silverstripe-urlsegmenter`

## Upgrading from 1.x/2.x to 3.x

There are new config variables, which maintain the same default effect, however if you are overriding them in your projects you will need to update to the new variable names:

```
private static $urlsegmenter_force_title = true;
private static $urlsegmenter_enable_field = false;
```

Are now toggled via:

```
private static $urlsegmenter_forced = true;
private static $urlsegmenter_cmsfield_enabled = false;
```

## Usage example

It's all plug-n-play once you apply the extension to your data object - with one exception.

To allow the extension to auto-generate a slug and manage collisions, you need to tell the extension what the scope of the collection you're setting the URLSegment within is.

```php
class Widget extends DataObject
{
    // or apply via config.yml
    private static $extensions = [
        URLSegmenter::class
    ];
    
    private static $db = [
        'Title' => 'Varchar'
    ];

    private static $has_one = [
        'WidgetCategory' => WidgetCategory::class
    ];
    
    public function getURLSegmenterScope()
    {
        return self::get()
            ->filter('WidgetCategoryID' => $this->WidgetCategoryID)
            ->exclude('ID', $this->ID);
    }
}
```
