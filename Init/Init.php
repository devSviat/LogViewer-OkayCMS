<?php


namespace Okay\Modules\Sviat\LogViewer\Init;


use Okay\Core\Modules\AbstractInit;

class Init extends AbstractInit
{
    public const PERMISSION = 'sviat__log_viewer';

    public function install()
    {
        $this->setBackendMainController('LogViewerAdmin');
    }

    public function init()
    {
        $this->addPermission(self::PERMISSION);

        $this->registerBackendController('LogViewerAdmin');
        $this->addBackendControllerPermission('LogViewerAdmin', self::PERMISSION);

        $this->extendBackendMenu('left_settings', [
            'sviat_log_viewer__menu_title' => ['LogViewerAdmin'],
        ]);
    }
}
