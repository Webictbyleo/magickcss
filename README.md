# magickcss
This is a PHP class that is used to parse stylesheet file into arrays. It also provides many functions to modify css files. 
##
Available methods
##
* load
* loadfile
* findSelector
* findSelectorClassList 
* findSelectorIDs
* matchSelector
* findSelectorTags
* removeSelector
* appendSelector
* addSelector
* findMediaSelector
* addMediaSelector
* addMedia
* appendMediaSelector
* removeMediaSelector
* removeMedia
* matchMedia
* findFont
* removeFont
* addFont
* removeImport
* addImport
* findImport
* findKeyFrame
* appendKeyframe
* addKeyframe
* removeKeyFrame
* export

####
Configuration options
* minify (boolean)
* remove_duplicate (boolean)
* remove_mediaquery (boolean)
* selector_prefix (string)
#
# Example
###
```
$css = new Magickcss;
```
###
## Set configuration options
###
```
$css->minify = true;
$css->selector_prefix = 'md';
```
###
### Load and modify file
```
$css->loadfile(CSS_FILE_LINK_HERE);
$css->removeSelector(['html','body','a']);
$not_allowed = $css->matchSelector('[class^=bg-],[class^=btn-],[class^=text-]');
$css->removeSelector($not_allowed);
## Remove all media query selectors
$css->removeMediaSelector();
## Remove specific media query
$css->removeMedia('min-width==200px');
$css->removeMedia('min-width >= 500px,max-width < 200');
$css->removeImport('animate,font-awesome');
$css->removeFont('roboto');
```
### Add and modify animation keyframe
```
$css->addKeyFrame('glow');
$css->appendKeyframe('glow','0%',['opacity'=>0,'width'=>0]);
$css->appendKeyframe('glow','100%',['opacity'=>1,'width'=>'100px']);
```

### Export to css file
```
$css_>remove_mediaquery = true;
$css->export('output_file.css');
```
