
![GitHub](https://img.shields.io/github/license/cashfree/cashfree-magento) ![Discord](https://img.shields.io/discord/931125665669972018?label=discord) ![GitHub last commit (branch)](https://img.shields.io/github/last-commit/cashfree/cashfree-magento/master) ![GitHub release (with filter)](https://img.shields.io/github/v/release/cashfree/cashfree-magento?label=latest) ![GitHub forks](https://img.shields.io/github/forks/cashfree/cashfree-magento)  ![GitHub Repo stars](https://img.shields.io/github/stars/cashfree/cashfree-magento)

## Installation

1. Download the `code.zip` file from the latest [release](https://github.com/cashfree/cashfree-magento/releases).
2. Extract the zip and navigate to the "app" directory.
3. If a "code" folder exists, overwrite its contents with the "code" folder from the zip file. If it does not exist, simply place the new "code" folder in app directory.
4. Execute the following commands in your Magento root folder to enable the Cashfree module:

```bash
bin/magento module:enable Cashfree_Cfcheckout
bin/magento setup:upgrade
```

Check if the module is installed with:

```bash
bin/magento module:status
```

`Cashfree_Cfcheckout` should appear in your module list.

## Configuration

Configure the Cashfree payment method in your Magento Admin:

- Navigate to **Admin** -> **Stores** -> **Configuration** -> **Sales** -> **Payment Method** -> **Cashfree**.

Try clearing your Magento Cache from your admin panel if you experienc any issues:

- Go to **System** -> **Cache Management** in the admin panel. 

## Version Compatibility

- For Magento version 2.3.x or above, refer to the [master branch](https://github.com/cashfree/cashfree-magento).
- For Magento version 2.2.x and earlier, download from the [magento-2.2.x branch](https://github.com/cashfree/cashfree-magento/tree/magento-2.2.x).

## Getting Help

If you encounter issues or have questions, feel free to reach out:

1. Submit an issue to our [GitHub Issue Tracker](https://github.com/cashfree/cashfree-magento/issues).
2. Send a message on our [Discord server](https://discord.gg/znT6X45qDS).
3. Email us at care@cashfree.com.

## Contributing

Want to contribute? Check out our [CONTRIBUTING](CONTRIBUTING.md) guidelines.

## Additional Information

- [Open Source License](LICENSE.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
