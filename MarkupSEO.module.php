<?php
/**
 * MarkupSEO
 * The all-in-one SEO solution for ProcessWire.
 *
 * By Nico Knoll (http://nico.is/)
 *
 * Major upgrades and bug fixes by Adrian Jones
 *
 */

class MarkupSEO extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return array(
            'title' => __('SEO'),
            'version' => '2.0.1',
            'summary' => __('The all-in-one SEO solution for ProcessWire.'),
            'autoload' => true,
            'requires' => array('ProcessWire>=2.4.0', 'PHP>=5.3.8')
        );
    }


    public $pageData = array();


    /**
     * Default configuration
     *
     */
    static public function getDefaultConfig() {
        return array(
            'sitename' 			=> '',
            'author' 			=> '',
            'title' 				=> '',
            'titleSmart' 		=> 'title',
            'description' 		=> '',
            'descriptionSmart' 	=> '',
            'image' 				=> '',
            'imageSmart' 		=> '',
            'titleFormat' 		=> '',
            'canonical' 			=> '',
            'canonicalProtocol' => 'auto',
            'robots' 			=> array('index', 'follow'),
            'custom' 			=> '',
            'custom_head' 			=> '',
            'custom_body' 			=> '',
            'includeGenerator' 	=> 1,
            'includeOpenGraph' 	=> 1,
            'includeTwitter' 	=> 1,
            'twitterUsername' 	=> '',
            'useParents' 		=> 0,
            'method' 			=> 'auto',
            'addWhitespace'		=> 1,
            'includeTemplates' 	=> array(),
            'usePermission' 		=> 0,
            'googleAnalytics' 	=> '',
            'googleAnalyticsAnonymizeIP' => false,
            'piwikAnalyticsUrl' 	=> '',
            'piwikAnalyticsIDSite' => '',
            'hardLimit'			=> 0,
            'titleLimit'			=> '60',
            'descriptionLimit'	=> '160',
            'truncateDescription' => 1
        );
    }

    static public function getDefaultFields() {
        return array(
            'seo_tab',
            'seo_title',
            'seo_og_title',
            'seo_description',
            'seo_og_description',
            'seo_image',
            'seo_robots',
            'seo_canonical',
            'seo_custom',
            'seo_custom_head',
            'seo_custom_body',
            'seo_tab_END'
        );
    }


    /**
     * Populate default configuration (will be overwritten after constructor with user's own configuration)
     *
     */
    public function __construct() {
        foreach(self::getDefaultConfig() as $key => $value) {
            $this->$key = $value;
        }
    }


         /**
     * Initializing the hooks
     *
     */
    public function init() {
        // frontend hooks
        $this->wire()->addHookAfter("Page::render", $this, 'hookMethodAuto');
    }


    public function ready() {

        if($this->wire('page')->process == 'ProcessPageEdit') {
            // get page id from edited page id url parameter or Tracy console pid parameter
            // the Tracy option allows for calling $page->seo in the admin from the Console panel
            $pid = $this->wire('input')->post('pid') ?: $this->wire('input')->get('id');
            $p = $this->wire('pages')->get($pid);
            if(!($p instanceof NullPage)) {
                if(in_array($p->template->name, $this->includeTemplates)) {
                    $this->wire()->addHookAfter("ProcessPageEdit::buildFormContent", $this, 'hookCustomizeSeoTab');
                }
            }
        }
        else {
            $p = $this->wire('page');
        }

        if($p->template != 'admin' && in_array($p->template->name, $this->includeTemplates)) {
            $this->wire()->addHookProperty("Page::seo", $this, 'hookFrontendPage');
            $this->wire()->addHookProperty("Page::customHead", $this, 'hookFrontendCustomHead');
            $this->wire()->addHookProperty("Page::customBody", $this, 'hookFrontendCustomBody');
            $this->wire()->addHookProperty("Config::seo", $this, 'hookFrontendConfig');
        }

    }


    /**
     * The hooking functions
     *
     */
    public function hookMethodAuto(HookEvent $event) {
        if($this->method != 'auto' || $this->wire('page')->template == 'admin' || !in_array($this->wire('page')->template->name, $this->includeTemplates)) return;

        // inject rendered meta tags into page
        $event->return = str_ireplace("</head>", $this->wire('page')->seo->render.$this->wire('page')->customHead.'</head>', $event->return);

        // inject rendered custom body into page
        $event->return = str_ireplace("</body>", $this->wire('page')->customBody.'</body>', $event->return);
    }


    public function hookCustomizeSeoTab(HookEvent $e) {
        $page = $e->object->getPage();
        $configData = $this->wire('modules')->getModuleConfigData($this);
        $pageData = $this->getPageData($page);

        $titleField = (empty($configData['useParents']) or $configData['useParents'] == true) ? array('seo_title') : $configData['titleSmart'];
        $title = '';
        foreach ($titleField as $field) {
            if (!empty($page->$field)) {
                $title = $page->$field;
                break;
            }
        }

        if(!$e->return->get('seo_tab')) return;

        // Add google preview
        $field = $this->wire('modules')->get("InputfieldMarkup");
        $field->label = $this->_("Google Preview");
        $field->description = $this->_('Updates while you type in a title or description.');
        $field->value = 	$this->javascriptCounter($configData['hardLimit'], $configData['titleLimit'], $configData['descriptionLimit']).
                                        $this->javascriptAutocomplete($page).
                                        $this->javascriptGooglePreview($pageData['title'], $page->seo_canonical, $pageData['description']).
                                        $this->getGooglePreview($pageData['title'], $page->seo_canonical, $pageData['description']); // add javascript, too
        $e->return->insertAfter($field, $e->return->get('seo_tab'));

    }


    /**
     * Generates a google styled preview for the SEO Tab
     *
     */
    private function getGooglePreview($title, $url, $description) {
        $page = $this->wire('pages')->get($this->wire('input')->get->id);

        $html  = '<div class="SEO_google_wrapper"><span class="SEO_google_title">'.($title ? $title : 'Title').'</span>';
        $html .= '<span class="SEO_google_link">'.($url ? $url : $page->httpUrl).'</span>';
        $html .= '<span class="SEO_google_description">'.($description ? substr($description, 0, 155) : 'This is just a short description.').'.</span></div>';
        $html .= '<style>
                    .SEO_google_wrapper{float:left;width:512px;margin:10px 0;}
                    .SEO_google_title{color:#1a0dab;clear:both;width:auto;float:left;font-family:arial,sans-serif;font-size:16px;}
                    .SEO_google_title:hover{text-decoration:underline;cursor:pointer;}
                    .SEO_google_link{height:17px;line-height:16px;color:#006621;font-style:normal;font-size:13px;clear:both;float:left;font-family:arial,sans-serif;overflow: hidden;width: 100%;text-overflow: ellipsis;}
                    .SEO_google_description{line-height:16px;color:#545454;font-style:normal;font-size:13px;clear:both;float:left;font-family:arial,sans-serif;}
                    </style>';

        return $html;
    }


    /**
     * Returns an object including all the data (mixed config and page)
     *
     */
    public function hookFrontendPage(HookEvent $event) {
        // get page seo data
        $this->pageData = $this->getPageData($event->object);
        $event->return = (object) $this->pageData;
    }


    private function getPageData($page, $format = true) {

        if($this->pageData && $format === true) return $this->pageData;

        $templateName = $page->template->name;
        $pageData = array();
        foreach($page->fields as $field) {
            if(preg_match("%^seo_(.*)%Uis", $field->name) && $field->name != 'seo_tab' && $field->name != 'seo_tab_END') {
                if($field->type instanceof FieldtypeImage) {
                    $pageData[str_replace('seo_', '', $field->name)] = $page->getUnformatted($field->name)->first();
                }
                else {
                    $pageData[str_replace('seo_', '', $field->name)] = $page->getUnformatted($field->name);
                }
            }
        }

        // get config seo data
        $configData = $this->wire('modules')->getModuleConfigData($this);

        // override styles for multisite module, if it's installed
        if ($multiSite = $this->wire('modules')->getModule('Multisite', array('noPermissionCheck' => true, 'noInit' => true))) {
            if ($this->wire('config')->MultisiteDomains && array_key_exists($multiSite->domain, $this->wire('config')->MultisiteDomains) && array_key_exists('markupSEO', $this->wire('config')->MultisiteDomains[$multiSite->domain])) {
                $configDataOverrides = $this->wire('config')->MultisiteDomains[$multiSite->domain]['markupSEO']; // get special site data
                $configData = array_merge($configData, $configDataOverrides); // merge module config data with config data for special site

                // override data in module scope, otherwise the one from module settings will be used
                foreach(self::getDefaultConfig() as $key => $value) {
                    if (array_key_exists($key, $configData)) $this->$key = $configData[$key];
                }
            }
        }

        foreach($pageData as $fieldKey => $fieldValue) {
            // if the field has content we can continue
            // Prevent canonical url being inherited as it should always default to current page URL not parent (@mrjcgoodwin)
			if($fieldValue != '' || $fieldKey == 'canonical') continue;
            
            // otherwise we try to add default content
            if($configData['useParents']) {
                // use parent data
                $pageData[$fieldKey] = $this->getParentValue($page, $fieldKey);
            } else {
                // use smart data or default data
                switch($fieldKey) {
                    case 'title':
                        if(isset($configData[$templateName.'_title']) && $configData[$templateName.'_title'] != '') {
                            $pageData['title'] = $configData[$templateName.'_title'];
                        }
                        elseif(isset($configData[$templateName.'_titleSmart']) && $configData[$templateName.'_titleSmart'] && $page->get(implode('|', $configData[$templateName.'_titleSmart'])) != '') {
                            $pageData['title'] = strip_tags($page->get(implode('|', $configData[$templateName.'_titleSmart'])));
                        }
                        elseif(isset($configData['title']) && $configData['title'] != '') {
                            $pageData['title'] = $configData['title'];
                        }
                        elseif(isset($configData['titleSmart']) && $configData['titleSmart'] && $page->get(implode('|', $configData['titleSmart'])) != '') {
                            $pageData['title'] = strip_tags($page->get(implode('|', $configData['titleSmart'])));
                        }
                    break;
                    case 'description':
                        if(isset($configData[$templateName.'_description']) && $configData[$templateName.'_description'] != '') {
                            $pageData['description'] = $configData[$templateName.'_description'];
                        }
                        elseif(isset($configData[$templateName.'_descriptionSmart']) && $configData[$templateName.'_descriptionSmart'] && $page->get(implode('|', $configData[$templateName.'_descriptionSmart'])) != '') {
                            $pageData['description'] = $this->truncateDescription(strip_tags($page->get(implode('|', $configData[$templateName.'_descriptionSmart']))), $configData['descriptionLimit']);
                        }
                        elseif(isset($configData['description']) && $configData['description'] != '') {
                            $pageData['description'] = $configData['description'];
                        }
                        elseif(isset($configData['descriptionSmart']) && $configData['descriptionSmart'] && $page->get(implode('|', $configData['descriptionSmart'])) != '') {
                            $pageData['description'] = $this->truncateDescription(strip_tags($page->get(implode('|', $configData['descriptionSmart']))), $configData['descriptionLimit']);
                        }
                    break;
                    case 'image':
                        if(isset($configData[$templateName.'_image']) && $configData[$templateName.'_image'] != '') {
                            $pageData['image'] = $this->createImageObject($configData[$templateName.'_image'], $page);
                        }
                        if(isset($configData[$templateName.'_imageSmart']) && $configData[$templateName.'_imageSmart'] && count($page->get(implode('|', $configData[$templateName.'_imageSmart']))) > 0) {
                            $imageFields = $page->get(implode('|', $configData[$templateName.'_imageSmart']));
                            $pageData['image'] = $page->getUnformatted(implode('|', $configData[$templateName.'_imageSmart']))->first();
                        }
                        elseif(isset($configData['image']) && $configData['image'] != '') {
                            $pageData['image'] = $this->createImageObject($configData[$templateName.'_image'], $page);
                        }
                        elseif(isset($configData['imageSmart']) && is_object($configData['imageSmart']) && count($page->get(implode('|', $configData['imageSmart']))) > 0) {
                            $imageFields = $page->get(implode('|', $configData['imageSmart']));
                            $pageData['image'] = $page->getUnformatted(implode('|', $configData['imageSmart']))->first();
                        }
                    break;
                    case 'custom':
                        if(isset($configData[$templateName.'_custom']) && $configData[$templateName.'_custom'] != '') {
                            $pageData['custom'] = $configData[$templateName.'_custom'];
                        }
                        elseif(isset($configData['custom']) && $configData['custom'] != '') {
                            $pageData['custom'] = $configData['custom'];
                        }
                    break;
                    case 'custom_head':
                        if(isset($configData[$templateName.'_custom_head']) && $configData[$templateName.'_custom_head'] != '') {
                            $pageData['custom_head'] = $configData[$templateName.'_custom_head'];
                        }
                        elseif(isset($configData['custom_head']) && $configData['custom_head'] != '') {
                            $pageData['custom_head'] = $configData['custom_head'];
                        }
                    break;
                    case 'custom_body':
                        if(isset($configData[$templateName.'_custom_body']) && $configData[$templateName.'_custom_body'] != '') {
                            $pageData['custom_body'] = $configData[$templateName.'_custom_body'];
                        }
                        elseif(isset($configData['custom_body']) && $configData['custom_body'] != '') {
                            $pageData['custom_body'] = $configData['custom_body'];
                        }
                    break;
                }
            }

        }


        // add generator
        if($configData['includeGenerator']) $pageData['generator'] = 'ProcessWire '.$this->wire('config')->version;

        // add author
        if($configData['author']) $pageData['author'] = $configData['author'];

        // add robots
        $pageDataRobots = array();
        if(isset($pageData['robots']) && is_object($pageData['robots']) && count($pageData['robots']) !== 0) {
            foreach($pageData['robots'] as $option) {
                $pageDataRobots[] = $option->title;
            }
        }
        elseif(isset($configData[$templateName.'_robots']) && is_object($configData[$templateName.'_robots']) && count($configData[$templateName.'_robots']) !== 0) {
            foreach($configData[$templateName.'_robots'] as $option) {
                $pageDataRobots[] = $option;
            }
        }
        elseif(isset($configData['robots']) && is_object($configData['robots']) && count($configData['robots']) !== 0) {
            foreach($configData['robots'] as $option) {
                $pageDataRobots[] = $option;
            }
        }
        $pageData['robots'] = implode(', ', $pageDataRobots);

        // add opengraph and canonical
        if(!$pageData['canonical']) {
            if($configData['canonicalProtocol'] == 'auto') {
                if($this->wire('config')->https == true) $configData['canonicalProtocol'] = 'https';
            }

            if($configData['canonicalProtocol'] == 'https') {
                $pageData['canonical'] = preg_replace('%^https?%i', 'https', $page->httpUrl);
            } else {
                $pageData['canonical'] = $page->httpUrl;
            }

        }

        //Handle relative canonical URLs (@mrjcgoodwin)
		//Use substr not str_starts_with to provide PHP 7 compatibility
		$canonicalStartsWith = substr( $pageData['canonical'], 0, 4 ) === "http";

		if($canonicalStartsWith != 'http') {
			$pageData['canonical'] = wire('pages')->get('/')->httpUrl.$pageData['canonical'];
		}

        if($configData['includeOpenGraph']) {
             // TODO: Add more options
            $pageData['og:site_name'] = $configData['sitename'];
            $pageData['og:title'] = $pageData['og_title'] ?: $pageData['title'];
            $pageData['og:url'] = $pageData['canonical'];
            $pageData['og:description'] = $pageData['og_description'] ?: $pageData['description'];
            $pageData['og:type'] = 'website';
            $pageData['og:image'] = $pageData['image'] ? $pageData['image']->httpUrl() : '';
        }

        // add twitter
        if($configData['includeTwitter']) {
            // TODO: Add more options
            $pageData['twitter:card'] = 'summary';
            $pageData['twitter:site'] = '@'.$configData['twitterUsername'];
            $pageData['twitter:title'] = $pageData['og:title'];
            $pageData['twitter:url'] = $pageData['canonical'];
            $pageData['twitter:description'] = $pageData['og:description'];
            $pageData['twitter:image'] = $pageData['image'] ? $pageData['image']->httpUrl() : '';
        }


        if($format) {
            $pageData['custom'] = (array)$this->parseCustom($pageData['custom']);
            $configData['custom'] = (array)$this->parseCustom($configData['custom']);

            $pageData['custom'] = array_merge($configData['custom'], $pageData['custom']);

            foreach($pageData['custom'] as $key => $value) {
                $pageData[$key] = $value;
            }
        }


        // add google analytics
        $googleAnalytics = '';
        if($configData['googleAnalytics']) {
            $googleAnalytics = '
            <!-- Google Analytics -->
            <script>
                (function(i,s,o,g,r,a,m){i[\'GoogleAnalyticsObject\']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                 })(window,document,\'script\',\'//www.google-analytics.com/analytics.js\',\'ga\');

                ga(\'create\', \''.$configData['googleAnalytics'].'\', \'auto\');
            ';
            // Add "anonymizeIp" option if activated
            if( $configData['googleAnalyticsAnonymizeIP'] ) {
                $googleAnalytics .= 'ga(\'set\', \'anonymizeIp\', true)';
            }
            $googleAnalytics .= ';
                ga(\'send\', \'pageview\');

            </script>
            <!-- End: Google Analytics -->';
        }

        $piwikAnalytics = '';
        if($configData['piwikAnalyticsUrl']) {
            $piwikAnalytics = '<!-- Piwik -->
            <script type="text/javascript">
                var _paq = _paq || [];
                (function(){ var u=(("https:" == document.location.protocol) ? "https://'.$configData['piwikAnalyticsUrl'].'/" : "http://'.$configData['piwikAnalyticsUrl'].'/");
                _paq.push([\'setSiteId\', '.$configData['piwikAnalyticsIDSite'].']);
                _paq.push([\'setTrackerUrl\', u+\'piwik.php\']);
                _paq.push([\'trackPageView\']);
                _paq.push([\'enableLinkTracking\']);
                var d=document, g=d.createElement(\'script\'), s=d.getElementsByTagName(\'script\')[0]; g.type=\'text/javascript\'; g.defer=true; g.async=true; g.src=u+\'piwik.js\';
                s.parentNode.insertBefore(g,s); })();
            </script>
            <!-- End Piwik Code -->';
        }


        // add "render"
        $renderedHead = '';
        foreach($pageData as $name => $content) {

            switch($name) {
                case 'custom':
                case 'custom_head':
                case 'custom_body':
                    break;
                case 'title':
                    if($this->titleFormat == '') break;
                    $renderedHead .= '<title>'.$this->parseTitle($page, $content).'</title>'.PHP_EOL;
                    break;
                case 'canonical':
                    $renderedHead .= '<link rel="canonical" href="'.$content.'" />'.PHP_EOL;
                    break;
                case 'image':
                    $renderedHead .= '<meta property="'.$name.'" content="'.$content.'" />'.PHP_EOL;
                    break;
                default:
                    if(strstr($name, 'og_')) {
                        // not sure why these og_ ones are here, but ignore because they are empty
                    }
                    elseif (strstr($name, 'og:')) {
                        $renderedHead .= '<meta property="'.$name.'" content="'.$content.'" />'.PHP_EOL;
                    }
                    else {
                        $renderedHead .= '<meta name="'.$name.'" content="'.$content.'" />'.PHP_EOL;
                    }
                    break;
            }

        }

        // replace whitespaces and add analytics code
        $renderedHead .= preg_replace('/^\s+|\s+$/m', '', $googleAnalytics).PHP_EOL;
        $renderedHead .= preg_replace('/^\s+|\s+$/m', '', $piwikAnalytics).PHP_EOL;

        if($this->addWhitespace) {
            $renderedHeadTmp = '';
            foreach(explode(PHP_EOL, $renderedHead) as $line) {
                $renderedHeadTmp .= "\t".$line.PHP_EOL;
            }
            $renderedHead = $renderedHeadTmp;
        }

        $pageData['render'] = $pageData['rendered'] = $renderedHead;

        return $pageData;
    }


    public function hookFrontendCustomHead(HookEvent $event) {
        $pageData = $this->getPageData($event->object);
        $event->return = $pageData['custom_head'].PHP_EOL;
    }


    public function hookFrontendCustomBody(HookEvent $event) {
        $pageData = $this->getPageData($event->object);
        $event->return = $pageData['custom_body'].PHP_EOL;
    }


    private function getParentValue($page, $what = '') {
        if($page->id == 1) return '';
        $parent = $page->parent();
        if($parent->get('seo_'.$what) != '') return $parent->get('seo_'.$what);
        return $this->getParentValue($parent, $what);
    }


    private function parseTitle($page, $title) {
        $tags = array(
            'title' => $title,
            'sitename' => $this->sitename
        );

        $return = $this->titleFormat;
        foreach($tags as $tag => $value) {
            $return = str_replace("{".$tag."}", $value, $return);
        }

        return $return;
    }


    /**
     * Truncate the description to a specific length and then truncate to avoid splitting any words.
     *
     */
    private function truncateDescription($str, $maxlen) {

        if(!$maxlen) return $str;

        // note: tags are not stripped if itemDescriptionLength == 0 and stripTags == true
        if($this->stripTags) $str = strip_tags($str);

        if(strlen($str) < $maxlen) return $str;

        $str = trim(substr($str, 0, $maxlen));

        // boundaries that we can end the summary with
        $boundaries = array('. ', '? ', '! ', ', ', '; ', '-');
        $bestPos = 0;

        foreach($boundaries as $boundary) {
            if(($pos = strrpos($str, $boundary)) !== false) {
                // find the boundary that is furthest in string
                if($pos > $bestPos) $bestPos = $pos;
            }
        }

        // determine if we should truncate to last punctuation or last space.
        // if the last punctuation is further away then 1/4th the total length, then we'll
        // truncate to the last space. Otherwise, we'll truncate to the last punctuation.
        $spacePos = strrpos($str, ' ');
        if($spacePos > $bestPos && (($spacePos - ($maxlen / 4)) > $bestPos)) $bestPos = $spacePos;

        if(!$bestPos) $bestPos = $maxlen;

        return trim(substr($str, 0, $bestPos+1));
    }


    private function parseCustom($custom) {

        if(trim($custom) == '') return;

        $return = array();
        $lines = explode("\n", $custom);
        foreach($lines as $line) {
            list($key, $value) = explode(':=', $line);
            $key = preg_replace('%[^A-Za-z0-9\-\.\:\_]+%', '', str_replace(' ', '-', trim($key)));
            $value = trim($this->wire('sanitizer')->text(html_entity_decode($value)));
            $return[$key] = $value;
        }

        return $return;
    }


    private function createImageObject($imageUrl, $page) {
        foreach($page->getFields() as $f) {
            if($f->type instanceof FieldtypeImage) {
                $imageField = $f->name;
                break;
            }
        }
        if(isset($imageField)) {
            return new Pageimage($page->getUnformatted($imageField), $imageUrl);
        }
        else {
            return $imageUrl;
        }
    }


    /**
     * Returns an object including all the data (only config/defaults)
     *
     */
    public function hookFrontendConfig(HookEvent $event) {
        $moduleData = $this->wire('modules')->getModuleConfigData($this);
        $moduleData['custom'] = (array)$this->parseCustom($moduleData['custom']);
        $moduleData['robots'] = is_array($moduleData['robots']) ? implode(', ', $moduleData['robots']) : $moduleData['robots'];

        $moduleData = array_merge($moduleData, $moduleData['custom']);

        $event->return = (object)$moduleData;
    }




    /**
     * Create the modules setting page
     *
     */
    public function getModuleConfigInputfields(array $data) {
        $modules = $this->wire('modules');
        $input = $this->wire('input');
        $fields = $this->wire('fields');
        $tmpTemplates = $this->wire('templates');
        foreach($tmpTemplates as $template) { // exclude system fields
            if($template->flags & Template::flagSystem) continue;
            $templates[] = $template;
        }

        // merge default config settings (custom values overwrite defaults)
        $defaults = self::getDefaultConfig();
        $data = array_merge($defaults, $data);


        // Add/remove seo fields from templates
        if($input->post->submit_save_module) {

            $includedTemplates = (array)$input->post->includeTemplates;

            foreach($templates as $template) {
                if(in_array($template->name, $includedTemplates)) {
                    if($template->hasField('seo_tab')) {
                        continue;
                    } else {
                        // add seo fields
                        $seoFields = self::getDefaultFields();
                        unset($seoFields[count($seoFields)-1]); // unset closing seo_tab_END

                        foreach($fields->find('sort=id') as $seoField) {
                            if(preg_match("%^seo_(.*)%Uis", $seoField->name) && !in_array($seoField->name, self::getDefaultFields())) {
                                array_push($seoFields, $seoField->name);
                            }
                        }

                        array_push($seoFields, 'seo_tab_END'); // add closing again

                        //add fields to template
                        foreach($seoFields as $templateField) {
                            $template->fields->add($fields->get($templateField));
                        }
                        $template->fields->save();
                    }
                } else {
                    if($template->hasField('seo_tab')) {
                        // remove seo fields
                        foreach($template->fields as $templateField) {
                            if(in_array($templateField->name, self::getDefaultFields())) {
                                $template->fields->remove($templateField);
                            }
                        }
                        $template->fields->save();
                    } else {
                        continue;
                    }
                }
            }

        }




        // this is a container for fields, basically like a fieldset
        $form = new InputfieldWrapper();

        // Included fields
        $field = $modules->get("InputfieldAsmSelect");
        $field->name = "includeTemplates";
        $field->label = __("Templates with SEO tab");
        $field->description = __("Choose the templates which should get a SEO tab.");
        foreach($templates as $template) $field->addOption($template->name);
        $field->value = $data['includeTemplates'];
        $field->notes = __('Be careful with this field. If you remove an entry all of it\'s "seo_*" fields get deleted (including the data).');
        $form->add($field);


        // Author
        $field = $modules->get("InputfieldText");
        $field->name = "author";
        $field->label = __("Author");
        $field->description = "";
        $field->value = $data['author'];
        $form->add($field);

        // Site Name
        $field = $modules->get("InputfieldText");
        $field->name = "sitename";
        $field->label = __("Site Name");
        $field->description = "";
        $field->value = $data['sitename'];
        $form->add($field);


        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = "Advanced";
        $fieldset->collapsed = Inputfield::collapsedNo;
        $form->add($fieldset);

        $field = $modules->get("InputfieldCheckbox");
        $field->name = "useParents";
        $field->label = __("Use parent's values if empty?");
        $field->description = __("Parent's values will be used as default if you don't define page specific meta data and leave the fields below blank and don't choose smart fields.");
        $field->attr('checked', $data['useParents'] == '1' ? 'checked' : '' );
        $fieldset->add($field);

        array_unshift($data['includeTemplates'], 'default');
        foreach($data['includeTemplates'] as $templateName) {

            $template = $this->wire('templates')->get($templateName);
            $templateName = str_replace('default', '', $templateName);

            $defaultsFieldset = $modules->get("InputfieldFieldset");
            $defaultsFieldset->label = (is_object($template) && $template->id && $template->label ? $template->label : ($templateName ?: 'Overall Site')) . ' Defaults';
            $defaultsFieldset->collapsed = Inputfield::collapsedYes;
            $defaultsFieldset->showIf = 'useParents=0';
            $fieldset->add($defaultsFieldset);

            // Default Title
            $field = $modules->get("InputfieldText");
            $field->name = trim($templateName."_title", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Title");
            $field->description = __("A good length for a title is 60 characters.");
            $field->value = $this->getSettingsValue('title', $templateName);
            $field->columnWidth = 50;
            $field->set('class', 'seo_autocomplete');
            $defaultsFieldset->add($field);

            $field = $modules->get("InputfieldAsmSelect");
            $field->name = trim($templateName."_titleSmart", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Smart Title");
            $field->description = __("We will use these fields (in this order) if you don't fill in the title field");
            foreach($fields->find('type=FieldtypePageTitle|FieldtypePageTitleLanguage|FieldtypeText|FieldtypeTextLanguage|FieldtypeTextarea|FieldtypeTextareaLanguage, tags!=seo') as $selectField) {
                if($template && !$template->hasField($selectField)) continue;
                $field->addOption($selectField->name);
            }
            $field->value = $this->getSettingsValue('titleSmart', $templateName);
            $field->columnWidth = 50;
            $defaultsFieldset->add($field);

            // Default Description
            $field = $modules->get("InputfieldText");
            $field->name = trim($templateName."_description", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Description");
            $field->description = __("A good length for a description is 160 characters.");
            $field->value = $this->getSettingsValue('description', $templateName);
            $field->columnWidth = 50;
            $field->set('class', 'seo_autocomplete');
            $defaultsFieldset->add($field);

            $field = $modules->get("InputfieldAsmSelect");
            $field->name = trim($templateName."_descriptionSmart", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Smart Description");
            $field->description = __("We will use these fields (in this order) if you don't fill in the description field");
            foreach($fields->find('type=FieldtypePageTitle|FieldtypePageTitleLanguage|FieldtypeText|FieldtypeTextLanguage|FieldtypeTextarea|FieldtypeTextareaLanguage, tags!=seo') as $selectField) {
                if($template && !$template->hasField($selectField)) continue;
                $field->addOption($selectField->name);
            }
            $field->value = $this->getSettingsValue('descriptionSmart', $templateName);
            $field->columnWidth = 50;
            $defaultsFieldset->add($field);

            // Default Image
            $field = $modules->get("InputfieldText");
            $field->name = trim($templateName."_image", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Image");
            $field->description = __("Enter the URL to an image.");
            $field->value = $this->getSettingsValue('image', $templateName);
            $field->columnWidth = 50;
            $defaultsFieldset->add($field);

            $field = $modules->get("InputfieldAsmSelect");
            $field->name = trim($templateName."_imageSmart", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Smart Image");
            $field->description = __("We will use the first image from the specified image field.");
            foreach($fields->find('type=FieldtypeImage|FieldtypeCroppableImage|FieldtypeImageFocusArea') as $selectField) {
                if($selectField->name == 'seo_image') continue;
                if(is_object($template) && !$template->hasField($selectField)) continue;
                $field->addOption($selectField->name);
            }
            $field->value = $this->getSettingsValue('imageSmart', $templateName);
            $field->columnWidth = 50;
            $defaultsFieldset->add($field);

            // Robots
            $field = $modules->get("InputfieldCheckboxes");
            $field->name = trim($templateName."_robots", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . $this->_("Robots");
            $field->description = __("The robots settings will tell search engines which data they are allowed to include/index.");
            $field->addOption('index');
            $field->addOption('follow');
            $field->addOption('archive');
            $field->addOption('noindex');
            $field->addOption('nofollow');
            $field->addOption('noarchive');
            $field->addOption('nosnippet');
            $field->addOption('noodp');
            $field->addOption('noydir');
            $field->value = $this->getSettingsValue('robots', $templateName);
            $defaultsFieldset->add($field);

            // Custom Meta Tags
            $field = $modules->get("InputfieldTextarea");
            $field->name = trim($templateName."_custom", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . __("Custom Meta Tags");
            $field->description = __("If you want to add other meta tags, you can do it here.");
            $field->notes = __('Please use this schema: name := content. One tag per line. Special characters are only allowed in the content part and get converted to HTML.');
            $field->value = $this->getSettingsValue('custom', $templateName);
            $field->collapsed = Inputfield::collapsedBlank;
            $defaultsFieldset->add($field);

            // Custom End Head
            $field = $modules->get("InputfieldTextarea");
            $field->name = trim($templateName."_custom_head", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . $this->_("Custom End Head");
            $field->description = $this->_("If you want to add other code to the <head>, you can do it here.");
            $field->notes = $this->_('You can enter anything here, like javascript tracking codes. Please be careful.');
            $field->value = $this->getSettingsValue('custom_head', $templateName);
            $field->collapsed = Inputfield::collapsedBlank;
            $defaultsFieldset->add($field);

            // Custom End Body
            $field = $modules->get("InputfieldTextarea");
            $field->name = trim($templateName."_custom_body", '_');
            $field->label = (is_object($template) && $template->id && $template->label ? $template->label : $templateName) . ' ' . $this->_("Custom End Body");
            $field->description = $this->_("If you want to add other code to the <body>, you can do it here.");
            $field->notes = $this->_('You can enter anything here, like javascript tracking codes. Please be careful.');
            $field->value = $this->getSettingsValue('custom_body', $templateName);
            $field->collapsed = Inputfield::collapsedBlank;
            $defaultsFieldset->add($field);

        }

        // title format
        $field = $modules->get("InputfieldText");
        $field->name = "titleFormat";
        $field->label = __("Title Format");
        $field->description = __("Use this field to adjust the title format. If left empty the <title> tag won't be included.");
        $field->value = $data['titleFormat'];
        $field->columnWidth = 50;
        $field->notes = __('You can use: {title}, {sitename}');
        $fieldset->add($field);


        // https or http format
        $field = $modules->get("InputfieldSelect");
        $field->name = "canonicalProtocol";
        $field->label = __("Protocol for canonical links");
        $field->description = __("Choose if you always want to use \"https\" or \"http\" or if you want automatic detection.");
        $field->notes = __('Automatic detection will check if $config->https is set to true.');
        $field->value = $data['canonicalProtocol'];
        $field->addOption('auto', 'Automatically');
        $field->addOption('http', 'http');
        $field->addOption('https', 'https');
        $field->required = true;
        $field->columnWidth = 50;
        $fieldset->add($field);


        // Limits
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "hardLimit";
        $field->label = __("Enforce hard limits?");
        $field->description = __('This toggles the hard limit for any defined limits. If checked it prevents more than the defined characters to be entered.');
        $field->attr('checked', $data['hardLimit'] == '1' ? 'checked' : '' );
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get("InputfieldInteger");
        $field->name = "titleLimit";
        $field->label = __("SEO title character limit");
        $field->description = __('The character limit for the SEO title, recommended and default is 60.');
        $field->value = $data['titleLimit'];
        $field->attr('min', '1');
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get("InputfieldInteger");
        $field->name = "descriptionLimit";
        $field->label = __("SEO description character limit");
        $field->description = __('The character limit for the SEO title, recommended and default is 160.');
        $field->value = $data['descriptionLimit'];
        $field->attr('min', '1');
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get("InputfieldCheckbox");
        $field->name = "truncateDescription";
        $field->label = __("Automatically truncate smart description");
        $field->description = __("Automatically truncate smart description to the defined character limit.");
        $field->attr('checked', $data['truncateDescription'] == '1' ? 'checked' : '' );
        $field->columnWidth = 25;
        $fieldset->add($field);


        // Include stuff
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "includeGenerator";
        $field->label = __("Include Generator?");
        $field->description = __('This will include a meta tag called "generator" to show that this site was created with "ProcessWire 2.x.x".');
        $field->attr('checked', $data['includeGenerator'] == '1' ? 'checked' : '' );
        $field->columnWidth = 33;
        $fieldset->add($field);

        $field = $modules->get("InputfieldCheckbox");
        $field->name = "includeOpenGraph";
        $field->label = __("Include (Basic) Open Graph?");
        $field->description = __('The Open Graph meta tags are prefered by Facebook and several other sites.');
        $field->attr('checked', $data['includeOpenGraph'] == '1' ? 'checked' : '' );
        $field->columnWidth = 34;
        $fieldset->add($field);

        $field = $modules->get("InputfieldCheckbox");
        $field->name = "includeTwitter";
        $field->label = __("Include (Basic) Twitter Cards?");
        $field->description = __('This will help Twitter to extract the right data from your site.');
        $field->attr('checked', $data['includeTwitter'] == '1' ? 'checked' : '' );
        $field->columnWidth = 33;
        $fieldset->add($field);

        $field = $modules->get("InputfieldText");
        $field->name = "twitterUsername";
        $field->label = __("Twitter Username");
        $field->description = __('Your Twitter username (without "@") is needed for the "include Twitter" option.');
        $field->value = $data['twitterUsername'];
        $field->showIf = 'includeTwitter=1';
        $fieldset->add($field);

        // Choose Method
        $field = $modules->get("InputfieldRadios");
        $field->name = "method";
        $field->label = __("Method");
        $field->description = __("Do you want to get the generated code included automatically in the <head> part of your site?");
        $field->addOption('auto', __('Automatically'));
        $field->addOption('manual', __('Manually'));
        $field->value = $data['method'];
        $field->columnWidth = 50;
        $fieldset->add($field);

        // Add Whitespace
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "addWhitespace";
        $field->label = __("Add whitespace before tags?");
        $field->description = __('This will add a little white space (one tab indent) before your meta tags in the "rendered" version. Perfectly if you use the automatically insert method.');
        $field->attr('checked', $data['addWhitespace'] == '1' ? 'checked' : '' );
        $field->columnWidth = 50;
        $fieldset->add($field);


        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = "Tracking";
        $fieldset->collapsed = Inputfield::collapsedNo;
        $form->add($fieldset);

        // Analytics code
        $field = $modules->get("InputfieldText");
        $field->name = "googleAnalytics";
        $field->label = __("Google Analytics Code");
        $field->description = __("Google Analytics code will be embedded if this field is populated.");
        $field->notes = __('How to find your code: https://support.google.com/analytics/answer/1008080. It should look like: UA-XXXXXXX-X.');
        $field->value = $data['googleAnalytics'];
        $field->columnWidth = 50;
        $fieldset->add($field);

        // Anonymize IP
        $field = $modules->get("InputfieldCheckbox");
        $field->name = "googleAnalyticsAnonymizeIP";
        $field->label = __("Google Analytics: Anonymize IPs?");
        $field->description = __('In some countrys (like Germany) anonymizing the visitors IP in Google Analytics is an obligatory setting for legal reasons.');
        $field->attr('checked', $data['googleAnalyticsAnonymizeIP'] == '1' ? 'checked' : '' );
        $field->columnWidth = 50;
        $fieldset->add($field);


        $field = $modules->get("InputfieldText");
        $field->name = "piwikAnalyticsUrl";
        $field->label = __("Piwik Analytics URL");
        $field->description = __("Piwik code will be embedded if both Piwik fields are populated.");
        $field->value = $data['piwikAnalyticsUrl'];
        $field->notes = __('Url without http:// or https://');
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $modules->get("InputfieldText");
        $field->name = "piwikAnalyticsIDSite";
        $field->label = __("Piwik Analytics IDSite");
        $field->description = __("Piwik code will be embedded if both Piwik fields are populated.");
        $field->value = $data['piwikAnalyticsIDSite'];
        $field->columnWidth = 50;
        $fieldset->add($field);


        $fieldset = $modules->get("InputfieldFieldset");
        $fieldset->label = "More...";
        $fieldset->collapsed = Inputfield::collapsedNo;
        $form->add($fieldset);

        $field = $modules->get("InputfieldMarkup");
        $field->label = __("Multilanguage");
        $line[] = __('If your site uses multiple languages you can use SEO fields in multiple languages to. To archive this, you have to change the fieldtypes of the SEO fields manually:');
        $line[] = __('1. Go to "Setup > Fields". There are a lot of fields in the schema "seo_*". Now click on the field you want to have in multiple languages.');
        $line[] = __('2. Change the "Type" e.g. from "Text" to "TextLanguage" and click "Save". That\'s it.');
        $field->value = implode('<br>', $line);
        $fieldset->add($field);


        $field = $modules->get("InputfieldMarkup");
        $field->label = __("Recommendations");
        $string = __('For an even better SEO experience there are a couple of other modules I can recommend:');
        $field->value = '<p>'.$string.'</p>
                        <ul>
                            <li><a target="_blank" href="http://modules.processwire.com/modules/markup-sitemap-xml/">Sitemap</a> (MarkupSitemapXML)</li>
                            <li><a target="_blank" href="http://modules.processwire.com/modules/page-path-history/">Page Path History</a> (PagePathHistory)</li>
                            <li><a target="_blank" href="http://modules.processwire.com/modules/search-engine-referrer-tracker/">Search Engine Referrer Tracker</a> (SearchEngineReferrerTracker)</li>
                            <li><a target="_blank" href="http://modules.processwire.com/modules/process-redirects/">Redirects</a> (ProcessRedirects)</li>
                            <li><a target="_blank" href="http://modules.processwire.com/modules/all-in-one-minify/">AIOM+</a> (AllInOneMinify)</li>
                        </ul>'.$this->javascriptAutocomplete(); // Add javascript

        $fieldset->add($field);


        $field = $modules->get("InputfieldMarkup");
        $field->label = __("Support development");
        $string = __('This is and stays a free, open-source module. If you like it and want to support its development you can use this button:');
        $field->value = '<p>'.$string.'</p>
                    <a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8RTGCB7NCWE2J"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"></a>';

        $fieldset->add($field);

        return $form;
    }

    static private function javascriptCounter($hardLimit, $titleLimit, $descriptionLimit) {
        $string = __('characters used');
        $code = "
        $(document).ready(function(){
            $('#Inputfield_seo_title').data('seolimit', ".$titleLimit.");
            $('#Inputfield_seo_description').data('seolimit', ".$descriptionLimit.");

            if(".($hardLimit ?: 0).") {
                $('#Inputfield_seo_title').attr('maxlength', ".$titleLimit.");
                $('#Inputfield_seo_description').attr('maxlength', ".$descriptionLimit.");
            }

            $('#Inputfield_seo_title, #Inputfield_seo_description').each(function(){
                $(this).before('<p class=\"counter notes\"><span class=\"remainingChars\">' + $(this).val().length + '</span>/' + $(this).data('seolimit') + ' ".$string.".</p>');

                $(this).on('change load propertychange keyup input paste', function(el){
                    $(this).siblings('.counter').children('.remainingChars').html($(this).val().length);
                });
            });
        });
        ";
        return '<script type="text/javascript">'.$code.'</script>';
    }

    private function getSettingsValue($name, $templateName) {
        if($templateName != '' && isset($this->data[$templateName.'_'.$name])) {
            $settingsValue = $this->data[$templateName.'_'.$name];
        }
        else {
            $settingsValue = $this->data[$name];
        }
        return $settingsValue;
    }

    private function javascriptAutocomplete($page = null) {

        $code = "
        $(document).ready(function(){
        ";

            if($page) {
                $pageData = $this->getPageData($page, false);

                $code .= "

                    $('#Inputfield_seo_title, #Inputfield_seo_og_title, #Inputfield_seo_description, #Inputfield_seo_og_description, #Inputfield_seo_robots, #Inputfield_seo_custom, #Inputfield_seo_custom_head, #Inputfield_seo_custom_body').each(function(ev) {
                        if(!$(this).val()) {
                            var fieldName = this.id.replace('Inputfield_seo_', '').replace('og_', 'og:');
                            var pageData = new Array(".json_encode($pageData).");
                            $(this).attr('placeholder', pageData[0][fieldName]);
                        }
                    });
                ";
            }

        $code .= "
            $('#Inputfield_seo_title, #Inputfield_seo_description, .seo_autocomplete').autocomplete({
                minLength: 2,
                source: function(request, response) {
                    var suggestions = $.ajax({url:'https://suggestqueries.google.com/complete/search',dataType:'jsonp',data:{q:request.term,cp:1,gs_id:6,xhr:'t',client:'youtube'}}).done(function(data){
                        response($.map(data[1], function(item) {
                            return {
                                label: item[0],
                                value: item[0]
                            }
                        }));
                    });
                }
            }).keydown(function(event) {
                if(event.keyCode == 13) {
                    // prevents enter from submitting the form
                    event.preventDefault();
                    return false;
                }
            });

        });
        ";
        return '<script type="text/javascript">'.$code.'</script>';
    }

    private function javascriptGooglePreview($title, $url, $description) {
        $configData = $this->wire('modules')->getModuleConfigData($this);

        $titleSmart = ($configData['useParents'] == true) ? array('seo_title') : $configData['titleSmart'];
        $smartFieldFormat = function($fieldName) {
            return "input[name={$fieldName}]";
        };
        $titleFieldsSelectors = implode(',',array_map($smartFieldFormat,$titleSmart));
        $titleFieldsNames = "'" . implode('\',\'',$titleSmart) ."'";

        $code = "
        $(document).ready(function(){

            $('{$titleFieldsSelectors},input[name=seo_title]').keyup(function(){
                $.each([$titleFieldsNames],function(index, name) {
                    value = $('input[name=seo_'+ name +']').val();
                    if (value != '') {
                        $('.SEO_google_title').html(value);
                        return false;
                    } else if (index == ($(this).length - 1)) {
                        $('.SEO_google_title').html('".addslashes($title)."');
                    }
                });
            });

            $('#Inputfield_seo_description').keyup(function(){
                $('.SEO_google_description').html(((\$(this).val()) ? \$(this).val() : '".addslashes($description)."'));
            });

            $('#Inputfield_seo_canonical').keyup(function(){
                $('.SEO_google_link').html(((\$(this).val()) ? \$(this).val() : '".$url."'));
            });

        });
        ";
        return '<script type="text/javascript">'.$code.'</script>';
    }


    /**
     * Install and uninstall functions
     *
     */

    public function ___install() {

        $fields = $this->wire('fields');

        // Tab stuff

        if(!$fields->get('seo_tab')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get('FieldtypeFieldsetTabOpen');
            $field->name = "seo_tab";
            $field->label = $this->_('SEO');
            $field->tags = 'seo';
            $field->save();
        }


        // title, description, image, canonical, robots, custom

        if(!$fields->get('seo_title')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeText");
            $field->name = "seo_title";
            $field->label = $this->_("Title");
            $field->description = $this->_("A good length for a title is 60 characters.");
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_og_title')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeText");
            $field->name = "seo_og_title";
            $field->label = $this->_("OG Title");
            $field->description = $this->_("A good length for a title is 60 characters.");
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_description')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeText");
            $field->name = "seo_description";
            $field->label = $this->_("Description");
            $field->description = $this->_("A good length for a description is 160 characters.");
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_og_description')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeText");
            $field->name = "seo_og_description";
            $field->label = $this->_("OG Description");
            $field->description = $this->_("A good length for a description is 160 characters.");
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_image')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeImage");
            $field->name = "seo_image";
            $field->label = $this->_("Image");
            $field->extensions = 'gif jpg jpeg png svg';
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_canonical')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeText");
            $field->name = "seo_canonical";
            $field->label = $this->_("Canonical Link");
            $field->description = $this->_("If left blank, canonical will default to current page URL.");
            $field->notes = $this->_('The URL should include "http://..." or you can use relative URLs for internal links. E.g. "foo/bar". (Omit proceeding "/").');
            $field->tags = 'seo';
            $field->save();
        }

        if(!$fields->get('seo_robots')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeOptions");
            $field->inputfieldClass = "InputfieldCheckboxes";
            $field->name = "seo_robots";
            $field->label = $this->_("Robots");
            $field->description = $this->_("The robots settings will tell search engines which data they are allowed to include/index.");
            $field->notes = $this->_('This overwrites the module\'s global setting for this page.');
            $field->tags = 'seo';
            $field->save();
            $value = "index\nfollow\narchive\nnoindex\nnofollow\nnoarchive\nnosnippet\nnoodp\nnoydir";
            $module = $this->wire('modules')->get('FieldtypeOptions');
            $module->manager->setOptionsString($field, $value, true);
        }


        if(!$fields->get('seo_custom')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeTextarea");
            $field->name = "seo_custom";
            $field->label = $this->_("Custom Meta Tags");
            $field->description = $this->_("If you want to add other meta tags, you can do it here.");
            $field->notes = $this->_('Please use this schema: name := content. One tag per line. Special characters are only allowed in the content part and get converted to HTML.');
            $field->collapsed = Inputfield::collapsedBlank;
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_custom_head')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeTextarea");
            $field->name = "seo_custom_head";
            $field->label = $this->_("Custom End Head");
            $field->description = $this->_("If you want to add other code to the <head>, you can do it here.");
            $field->notes = $this->_('You can enter anything here, like javascript tracking codes. Please be careful.');
            $field->collapsed = Inputfield::collapsedBlank;
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_custom_body')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get("FieldtypeTextarea");
            $field->name = "seo_custom_body";
            $field->label = $this->_("Custom End Body");
            $field->description = $this->_("If you want to add other code to the <body>, you can do it here.");
            $field->notes = $this->_('You can enter anything here, like javascript tracking codes. Please be careful.');
            $field->collapsed = Inputfield::collapsedBlank;
            $field->tags = 'seo';
            $field->save();
        }


        if(!$fields->get('seo_tab_END')) {
            $field = new Field;
            $field->type = $this->wire('modules')->get('FieldtypeFieldsetClose');
            $field->name = "seo_tab_END";
            $field->label = $this->_('Close an open fieldset');
            $field->tags = 'seo';
            $field->save();
        }

    }


    public function ___uninstall() {
        $fields = $this->wire('fields');
        $templates = $this->wire('templates');

        foreach(self::getDefaultFields() as $field) {
            foreach($templates as $template) {
                if(!$template->hasField($field)) continue;
                $template->fields->remove($field);
                $template->fields->save();
            }
            if($fields->get($field)) $fields->delete($fields->get($field));
        }

    }
}
