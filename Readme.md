### Install through "code-2.3.x.zip" file

Extract the attached code-2.3.x.zip from release

Go to "app" folder

Overwrite content of "code" folder with step one "code" folder (Note: if code folder not exist just place the code folder from step-1).

Run from magento root folder.

```
bin/magento module:enable Cashfree_Cfcheckout
bin/magento setup:upgrade
```

You can check if the module has been installed using `bin/magento module:status`

You should be able to see `Cashfree_Cfcheckout` in the module list

### Configuration

Go to Admin -> Stores -> Configuration -> Sales -> Payment Method -> Cashfree to configure Cashfree

Please try clearing your Magento Cache from your admin panel (System -> Cache Management) if you are experiencing any issues.

### Note:
- Refer [master](https://github.com/cashfree/cashfree-magento) for magento 2.3.x and latest version.
- For Magento 2.2.x and lower version, please download [magento-2.2.x](https://github.com/cashfree/cashfree-magento/tree/magento-2.2.x) branch