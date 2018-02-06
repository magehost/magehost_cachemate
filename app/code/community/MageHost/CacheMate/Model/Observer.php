<?php
/**
 * MageHost_CacheMate
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this Module to
 * newer versions in the future.
 *
 * @category     MageHost
 * @package      MageHost_CacheMate
 * @copyright    Copyright (c) 2018 MageHost.pro (https://magehost.pro)
 */

class MageHost_CacheMate_Model_Observer extends Mage_Core_Model_Abstract
{
    const CONFIG_SECTION = 'magehost_cachemate';
    const FLUSH_LOG_FILE = 'cache_flush.log';
    const MISS_LOG_FILE  = 'cache_miss.log';
    const TAGS_LOG_FILE  = 'cache_tags.log';

    /** @var null|string */
    var $logSuffix = null;
    /** @var string */
    var $currentUrl = null;
    /** @var string */
    var $filterUrl = array();

    /**
     * Event listener to filter cache flushes
     * @param Varien_Event_Observer $observer
     * @throws Zend_Cache_Exception
     */
    public function cleanBackendCache( $observer ) {
        /** @noinspection PhpUndefinedMethodInspection */
        $transport = $observer->getTransport();
        /** @noinspection PhpUndefinedMethodInspection */
        $tags = $transport->getTags();
        if ( !is_array($tags) ) {
            return;
        }
        $prefix = Mage::app()->getCacheInstance()->getFrontend()->getOption('cache_id_prefix');
        $oldTags = $tags;
        $doFilter = true;
        $changed = false;

        if ( $request = Mage::app()->getRequest() ) {
            if ('adminhtml' == $request->getRouteName() && 'cache' == $request->getControllerName()) {
                // We will always allow System > Cache Management
                $doFilter = false;
            }
        }
        if ( !empty($_SERVER['SCRIPT_FILENAME']) ) {
            $baseScript = basename($_SERVER['SCRIPT_FILENAME']);
            if ( 'n98-magerun.phar' == $baseScript || 'n98-magerun' == $baseScript || 'n98'  == $baseScript ) {
                // We will always allow N98 Magerun
                $doFilter = false;
            }
        }

        if ( $doFilter ) {
            if ( empty($tags) && ! Mage::getStoreConfigFlag( self::CONFIG_SECTION . '/flushes/_without_tags' )) {
                $changed = true; // so we will check if empty later on
            }
            $filters = array( 'catalog_product',
                              'catalog_category',
                              'cms_page',
                              'cms_block',
                              'translate',
                              'store',
                              'website',
                              'block_html',
                              'mage' );
            foreach ($filters as $filter) {
                if ( ! Mage::getStoreConfigFlag( self::CONFIG_SECTION . '/flushes/' . $filter ) ) {
                    $newTags = array();
                    foreach ($tags as $tag) {
                        if ( 0 !== stripos( $tag, $prefix . $filter ) && 
                             0 !== stripos( $tag, $filter ) ) {
                            $newTags[ ] = $tag;
                        } else {
                            $changed = true;
                        }
                    }
                    $tags = $newTags;
                }
            }
            if ( $changed && empty($tags) ) {
                $tags[] = 'MAGEHOST_BLOCKCACHE_DUMMY_TAG';
            }
        }

        if ( Mage::getStoreConfigFlag(self::CONFIG_SECTION.'/logging/flushes') && class_exists('Zend_Log') ) {
            $message = 'Magento cache flush.  Tags:' . $this->logTags($oldTags,$prefix);
            if ( $changed ) {
                $message .= '  AfterFilter:' . $this->logTags($tags,$prefix);
            }
            if ( !$doFilter ) {
                $message .= '  Filter is disabled for this request';
            }
            $message .= $this->getLogSuffix();
            Mage::log( $message, Zend_Log::INFO, self::FLUSH_LOG_FILE );
            Mage::register('MageHost_CacheMate_Logged',__FUNCTION__,true);
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $transport->setTags($tags);
    }

    /**
     * Event listener for simple cache flush events, without extra data
     * @param Varien_Event_Observer $observer
     */
    public function cacheFlushEvent($observer) {
        static $eventLogMessage = array(
            'adminhtml_cache_flush_system' => '[Flush Magento Cache] via System > Cache Management',
            'adminhtml_cache_flush_all' => '[Flush Cache Storage] via System > Cache Management',
            'adminhtml_cache_refresh_type' => 'Refresh via System > Cache Management',
            'application_clean_cache' => 'Clean application cache',
            'core_clean_cache' => 'Core cache clean cronjob',
            'clean_configurable_swatches_cache_after' => 'Clean Swatch Images cache',
            'clean_media_cache_after' => 'Clean JavaScript/CSS cache',
            'clean_catalog_images_cache_after' => 'Clean Catalog Images cache',
            'turpentine_varnish_flush_all' => 'Turpentine full flush',
            'turpentine_varnish_flush_partial' => 'Turpentine partial flush',
            'turpentine_varnish_flush_content_type' => 'Turpentine flush content type',
            'turpentine_ban_product_cache' => 'Turpentine ban Product cache',
            'turpentine_ban_product_cache_check_stock' => 'Turpentine ban Product Stock',
            'turpentine_ban_category_cache' => 'Turpentine ban Category cache',
            'turpentine_ban_media_cache' => 'Turpentine ban Media cache',
            'turpentine_ban_catalog_images_cache' => 'Turpentine ban Catalog Images cache',
            'turpentine_ban_cms_page_cache' => 'Turpentine ban CMS page cache',
            'turpentine_ban_all_cache' => 'Turpentine ban all cache',
            'turpentine_ban_esi_cache' => 'Turpentine ban ESI cache',
        );
        if ( Mage::getStoreConfigFlag(self::CONFIG_SECTION.'/logging/flushes')
             && class_exists('Zend_Log') ) {
            $eventName = $observer->getEvent()->getName();
            if ( array_key_exists($eventName,$eventLogMessage) ) {
                $message = $eventLogMessage[$eventName] . '.';
            } else {
                $message = $observer->getEvent()->getName() . '.';
            }
            foreach( $observer->getData() as $key => $value ) {
                if ( $key == 'event' ) {
                    // skip
                }
                elseif ( $key == 'tags' ) {
                    if (empty($value)) {
                        $value = '-empty-';
                    } elseif (is_array($value)) {
                        $value = implode(',',$value);
                    }
                    $message .= sprintf('  Tags:%s', $value);
                }
                elseif (preg_match('/^[\-\w]+(\.[\-\w]+)+:\d+$/',$key)) {
                    // Turpentine IP+Port combination
                }
                elseif (is_scalar($value)) {
                    $message .= sprintf('  %s=%s', $key, $value);
                }
            }
            /** @var Mage_Core_Controller_Request_Http $request */
            $request = Mage::app()->getRequest();
            $params = $request->getParams();
            if (isset($params['id'])) {
                $message .= sprintf('  ID=%d', $params['id']);
            }
            if (isset($params['page_id'])) {
                $message .= sprintf('  PageID=%d', $params['page_id']);
            }
            $message .= $this->getLogSuffix();
            Mage::log( $message, Zend_Log::DEBUG, self::FLUSH_LOG_FILE );
        }
    }

    /**
     * Event listener used to log cache misses
     * @param Varien_Event_Observer $observer
     */
    public function cacheMiss( $observer ) {
        $id = $observer->getId();
        if ( Mage::getStoreConfigFlag(self::CONFIG_SECTION.'/logging/misses') && class_exists('Zend_Log') ) {
            $message = 'Cache miss.  Id:' . $id;
            $message .= $this->getLogSuffix();
            Mage::log( $message, Zend_Log::INFO, self::MISS_LOG_FILE );
        }
    }

    /**
     * @return string
     */
    protected function getCurrentUrl() {
        if ( empty( $this->currentUrl ) ) {
            if ( version_compare( Mage::getVersion(), '1.4.2', '>=' ) ) {
                $this->currentUrl = Mage::helper('core/url')->getCurrentUrl();
            } else {
                // The getCurrentUrl() of Magento older then 1.4.2 is a bit too complicated to work during early events.
                // Warning: this fix does not guarantee the whole extension will work in Magento pre 1.6.2, use at own risk.
                $request = Mage::app()->getRequest();
                $port = $request->getServer('SERVER_PORT');
                if ($port) {
                    $port = ($port==80 || $port==443) ? '' : ':' . $port;
                }
                $this->currentUrl = $request->getScheme() . '://' . $request->getHttpHost() . $port . $request->getServer('REQUEST_URI');
            }
        }
        return $this->currentUrl;
    }

    /**
     * Form array of tags for output in log.
     * @param array $tags
     * @param string $prefix
     * @return string
     */
    protected function logTags( $tags, $prefix='' ) {
        if ( empty($tags) ) {
            return '-empty-';
        } else {
            if ( $prefix ) {
                $preg      = '/^' . preg_quote( $prefix, '/' ) . '/';
                $cleanTags = array();
                foreach ($tags as $tag) {
                    $cleanTags[ ] = preg_replace( $preg, '', $tag );
                }
            } else {
                $cleanTags = $tags;
            }
            return implode(',', $cleanTags);
        }
    }

    /**
     * Get a suffix string to add to logging lines which tells what is happening
     * @return string
     */
    protected function getLogSuffix()
    {
        if (is_null( $this->logSuffix )) {
            $this->logSuffix = '';
            $this->logSuffix .= '  Pid:' . getmypid();
            if ($request = Mage::app()->getRequest()) {
                if ($action = $request->getActionName()) {
                    $this->logSuffix .= '  Action:' . $request->getModuleName() . '/' . $request->getControllerName(
                        ) . '/' . $action;
                } elseif ($pathInfo = $request->getPathInfo()) {
                    $this->logSuffix .= '  PathInfo:' . $pathInfo;
                }
            }
            if (!empty( $_SERVER[ 'argv' ] )) {
                $this->logSuffix .= sprintf("  CommandLine:'%s'", implode( ' ', $_SERVER[ 'argv' ] ) );
            }
            $cronTask = Mage::registry('current_cron_task');
            if ( !empty($cronTask) ) {
                $this->logSuffix .= '  CronJob:' . $cronTask->getJobCode();
            }
        }
        return strval($this->logSuffix);
    }

    /**
     * @param  string|null $url
     * @return bool
     */
    protected function isFlushUrl( $url = null ) {
        if ( is_null($url) ) {
            $url = $this->getCurrentUrl();
        }
        return ( preg_match( '/\?.*mhflush/', $url ) || !empty( $_COOKIE['mhflush'] ) );
    }


    protected function isAdmin( $url=null ) {
        if ( is_null($url) ) {
            $url = Mage::app()->getRequest()->getPathInfo();
        }
        $adminFrontName = strval( Mage::getConfig()->getNode( 'admin/routers/adminhtml/args/frontName' ) );
        return ( false !== strpos( $url, '/' . $adminFrontName . '/' ) );
    }
}