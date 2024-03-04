<?php

namespace Cashfree\Cfcheckout\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Filesystem\DriverInterface;
/**
 *  Used to display webhook url link
 */
class LogHandler extends Base
{
    /**
     * File name
     * @var string
     */
    public $fileName = '';
    /**
     * File name
     * @var string
     */
    public $cutomfileName = 'NO_PATH';
    /**
     * @var TimezoneInterface
     */
    protected $_localeDate;

    public function __construct(
        DriverInterface   $filesystem,
        Filesystem        $corefilesystem,
        TimezoneInterface $localeDate,
                          $filePath = null
    ) {
        $this->_localeDate  = $localeDate;
        $corefilesystem     = $corefilesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $logpath            = $corefilesystem->getAbsolutePath('log/Cashfree/');


        // Custom log file name for each day because log will be full for optimization
        $filename = 'cf_'.Date('Y_m_d').'.log';

        $filepath = $logpath . $filename;

        $this->cutomfileName = $filepath;

        parent::__construct(
            $filesystem,
            $filepath
        );

    }
}
