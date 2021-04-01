### Install through version-1.0.0 branch

Extract the attached cashfree-magento-version-1.0.0.zip 

Go to "app/code" folder

Copy and pase "Cashfree" folder into code folder (Note: if code folder not exist, create a new folder name code).

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

### Note: This installation work only in Magento 2.x.
