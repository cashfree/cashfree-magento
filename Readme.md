### Install through "code.zip" file

Download the latest repository as .zip file from code > Download Zip 

Extract the Downloaded .zip file

Go to "app" folder of your magento installation

Create new Folder Named "Cashfree" inide the code folder

if there is no folder named "code". Please proceed creating one.  

Paste the extracted files inside the Cashfree. 

Finally the directory will look like 

###### app>code>Cashfree

Return to magento root folder.

Execute the following Commands

```
bin/magento module:enable Cashfree_Cfcheckout
bin/magento setup:upgrade
```

Check for installed modules using 
`bin/magento module:status`

You should be able to see `Cashfree_Cfcheckout` in the module list

### Configuration

Go to Admin -> Stores -> Configuration -> Sales -> Payment Method -> Cashfree to configure Cashfree

Please try clearing your Magento Cache from your admin panel (System -> Cache Management) if you are experiencing any issues.

### Note:
- This installation work only in Magento 2.x.
- For Magento 1.9 please download `master-1.9` branch