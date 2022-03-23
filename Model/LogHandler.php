<?php 

namespace Cashfree\Cfcheckout\Model;

use Monolog\Logger;
use Magento\Framework\Filesystem\DriverInterface;
/**
 *  Used to display webhook url link
 */
class LogHandler extends \Magento\Framework\Logger\Handler\Base
{    
     /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

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
        DriverInterface $filesystem,
        \Magento\Framework\Filesystem $corefilesystem,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        $filePath = null
    ) {
        $this->_localeDate  = $localeDate;
        $corefilesystem     = $corefilesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR); 
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
