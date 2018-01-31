## MageHost_CacheMate
Performance improvement by caching big blocks in Magento.

**We are sorry but we can only offer customer support for this extension to [MageHost.pro](https://magentohosting.pro) customers. To others it is provided "as-is" for free.**

This extension is meant to speed up shops which are not running on Varnish or any other full page cache.
It works by putting large blocks in the Magento block cache, for example the content area of catergory pages. The block cache can be stored on disk or in Redis.

### Installation

* [Please follow this manual.](https://github.com/magehost/magehost_cachemate/blob/master/INSTALL.md)
* [Upgrade from JeroenVermeulen CacheMate](https://github.com/magehost/magehost_cachemate/blob/master/UPGRADE.md)

### Config Settings:

`System > Configuration > ADVANCED > MageHost CacheMate`

### Cache Big Blocks
This Magento Extension adds functionality to cache these blocks which are normally not cached in Magento:

* Category List Page: Whole content area
* Product Detail Page: Whole content area
* CMS Page: Whole content area
* Cms Static Blocks & Widgets (disabled by default)

### Log & Filter Cache Flushes
This Extension also adds functionality to log & filter cache flushes:

* On Category change
* On Product change
* On CMS Page change
* On CMS Block change
* On Translation change
* On Store setting change
* On Website settings change
* Flush BLOCK_HTML
* Entire Magento Cache
* Wipe Cache Storage

It does this by extending [Cm_Cache_Backend_File](https://github.com/colinmollenhour/Cm_Cache_Backend_File) or [Cm_Cache_Backend_Redis](https://github.com/colinmollenhour/Cm_Cache_Backend_Redis)

### Config Screenshot
![Config Screenshot](docs/config.png)
