
![GitHub](https://img.shields.io/github/license/cashfree/cashfree-magento) ![Discord](https://img.shields.io/discord/931125665669972018?label=discord) ![GitHub last commit (branch)](https://img.shields.io/github/last-commit/cashfree/cashfree-magento/master) ![GitHub release (with filter)](https://img.shields.io/github/v/release/cashfree/cashfree-magento?label=latest) ![GitHub forks](https://img.shields.io/github/forks/cashfree/cashfree-magento)  ![GitHub Repo stars](https://img.shields.io/github/stars/cashfree/cashfree-magento)



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


## Getting help

If you have questions, concerns, bug reports, etc, you can reach out to us using one of the following

1. File an issue in this repository's Issue Tracker.
2. Send a message in our discord channel. Join our [discord server](https://discord.gg/znT6X45qDS) to get connected instantly.
3. Send an email to care@cashfree.com

## Getting involved

For general instructions on _how_ to contribute please refer to [CONTRIBUTING](CONTRIBUTING.md).


----

## Open source licensing and other misc info
1. [LICENSE](https://github.com/cashfree/cashfree-magento/blob/master/LICENSE.md)
2. [CODE OF CONDUCT](https://github.com/cashfree/cashfree-magento/blob/master/CODE_OF_CONDUCT.md)
