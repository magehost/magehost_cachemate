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
            $message = 'Cache flush.  Tags:' . $this->logTags($oldTags,$prefix);
            if ( $changed ) {
                $message .= '  AfterFilter:' . $this->logTags($tags,$prefix);
            }
            if ( !$doFilter ) {
                $message .= '  Filter is disabled for this request';
            }
            $message .= $this->getLogSuffix();
            Mage::log( $message, Zend_Log::INFO, self::FLUSH_LOG_FILE );
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $transport->setTags($tags);
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