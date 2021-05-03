### Installation

Extract the attached master-1.9.zip

Go to magento root folder

Overwrite content of "app" folder with step one "app" folder 

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

## Note:
- This installation work only in Magento 1.9.
- For Magento 2.x please download `master` branch or Release version `v2.0.1`