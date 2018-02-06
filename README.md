## MageHost_CacheMate
Log and filter cache flushes.

**We are sorry but we can only offer customer support for this extension to [MageHost.pro](https://magehost.pro) customers. To others it is provided "as-is" for free.**

### Installation

* [Please follow this manual.](https://github.com/magehost/magehost_cachemate/blob/master/INSTALL.md)

### Config Settings:

`System > Configuration > ADVANCED > MageHost CacheMate`

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

It does this by extending [Cm_Cache_Backend_File](https://github.com/colinmollenhour/Cm_Cache_Backend_File) or [Cm_Cache_Backend_Redis](https://github.com/colinmollenhour/Cm_Cache_Backend_Redis).

Additionally this extension logs flushes to [Turpentine](https://github.com/nexcess/magento-turpentine). 
