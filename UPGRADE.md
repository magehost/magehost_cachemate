## Upgrade Manual for the MageHost_CacheMate extension

### Upgrade from JeroenVermeulen-CacheMate

Using [Modman](https://github.com/colinmollenhour/modman):

1. Replace old extension copy by symlinks (if installed using --force)<br />
  `modman deploy --force jeroenvermeulen-cachemate`
* Remove old extension<br />
  `modman remove jeroenvermeulen-cachemate`
* In Magento Admin: _Flush Cache Storage_ or via [N98](https://github.com/netz98/n98-magerun): `n98-magerun.phar cache:flush`
* Install new extension<br />
  `modman clone --copy --force https://github.com/magehost/magehost_cachemate.git`
* In Magento Admin: _Flush Cache Storage_ or via [N98](https://github.com/netz98/n98-magerun): `n98-magerun.phar cache:flush`
* Logout from Magento Admin, login again
* Restore configuration via: _System > Configuration > ADVANCED > MageHost CacheMate_

### Upgrade from older MageHost_CacheMate version

Using [Modman](https://github.com/colinmollenhour/modman):

1. `modman update --copy --force magehost_cachemate`
* In Magento Admin: _Flush Cache Storage_
