<?php

namespace GettextTranslator;

use Latte\Loaders\FileLoader;
use Nette;
use Nette\Application\Application;
use Nette\Http\Request;
use Nette\Http\Session;
use Tracy\Debugger;
use Tracy\IBarPanel;

class Panel implements IBarPanel
{
    use Nette\SmartObject;

    /** @var string */
    private $xhrHeader = 'X-Translation-Client';

    /** @var string */
    private $languageKey = 'X-GettextTranslator-Lang';

    /** @var string */
    private $fileKey = 'X-GettextTranslator-File';

    /** @var string */
    private $layout;

    /** @var int */
    private $height;

    /** @var Application */
    private $application;

    /** @var Webwings\Gettext\Translator\Gettext */
    private $translator;

    /** @var Nette\Http\SessionSection */
    private $sessionStorage;

    /** @var Request */
    private $httpRequest;

    /**
     * @param Application
     * @param Gettext\Translator\Gettext
     * @param Session
     * @param Request
     * @param string
     * @param int
     */
    public function __construct(Application $application, Gettext $translator, Session $session, Request $httpRequest, $layout, $height)
    {
        $this->application = $application;
        $this->translator = $translator;
        $this->sessionStorage = $session->getSection(Gettext::$namespace);
        $this->httpRequest = $httpRequest;
        $this->height = $height;
        $this->layout = $layout;

        $this->processRequest();
    }

    /**
     * Return's panel ID
     * @return string
     */
    public function getId()
    {
        return __CLASS__;
    }

    /**
     * Returns the code for the panel tab
     * @return string
     */
    public function getTab()
    {
        $latte = new \Latte\Engine;
        $latte->setLoader(new FileLoader);

        $template = new \Nette\Bridges\ApplicationLatte\Template($latte);
        $template->setFile(__DIR__ . '/tab.latte');
        return $template;
    }

    /**
     * Returns the code for the panel itself
     * @return string
     */
    public function getPanel()
    {
        $files = array_keys($this->translator->getFiles());

        $strings = $this->translator->getStrings();
        $untranslatedStack = isset($this->sessionStorage['stack']) ? $this->sessionStorage['stack'] : [];
        foreach ($strings as $string => $data) {
            if (!$data) {
                $untranslatedStack[$string] = false;
            }
        }
        $this->sessionStorage['stack'] = $untranslatedStack;

        foreach ($untranslatedStack as $string => $value) {
            if (!isset($strings[$string])) {
                $strings[$string] = false;
            }
        }

        $latte = new \Latte\Engine;
        $latte->setLoader(new FileLoader);

        $template = new \Nette\Bridges\ApplicationLatte\Template($latte);
        $template->setFile(__DIR__ . '/panel.latte');

        $template->translator = $this->translator;
        $template->ordinalSuffix = function ($count) {
            switch (substr($count, -1)) {
                case '1':
                    return 'st';
                    break;
                case '2':
                    return 'nd';
                    break;
                case '3':
                    return 'rd';
                    break;
                default:
                    return 'th';
                    break;
            }
        };

        $template->application = $this->application;
        $template->strings = $strings;
        $template->height = $this->height;
        $template->layout = $this->layout;
        $template->files = $files;
        $template->xhrHeader = $this->xhrHeader;
        $template->activeFile = $this->getActiveFile($files);
        $this->translator->saveCatalog(Gettext::SCAN_FILE_SECTION);
        return $template;
    }


    /**
     * Handles an incomuing request and saves the data if necessary.
     */
    private function processRequest()
    {
        if ($this->httpRequest->isMethod('POST') && $this->httpRequest->isAjax() && $this->httpRequest->getHeader($this->xhrHeader)) {
            $data = json_decode(file_get_contents('php://input'));

            if ($data) {
                if ($this->sessionStorage) {
                    $stack = isset($this->sessionStorage['stack']) ? $this->sessionStorage['stack'] : [];
                }

                $this->translator->setLang($data->{$this->languageKey});
                $file = $data->{$this->fileKey};
                unset($data->{$this->languageKey}, $data->{$this->fileKey});

                foreach ($data as $string => $value) {
                    $this->translator->setTranslation($string, $value, $file);
                    if ($this->sessionStorage && isset($stack[$string])) {
                        unset($stack[$string]);
                    }
                    $this->translator->updatePOFile($file, $string, $string, $value);
                }
                $this->translator->save($file);

                if ($this->sessionStorage) {
                    $this->sessionStorage['stack'] = $stack;
                }
            }

            exit;
        }
    }

    /**
     * Register this panel
     * @param Application
     * @param Webwings\Gettext\Translator\Gettext
     * @param Session
     * @param Request
     * @param int $layout
     * @param int $height
     */
    public static function register(Application $application, Gettext $translator, Session $session, Request $httpRequest, $layout, $height)
    {
        Debugger::getBar()->addPanel(new static($application, $translator, $session, $httpRequest, $layout, $height));
    }

    /**
     * Get active file name
     * @param array
     * @return string
     */
    private function getActiveFile($files)
    {
        $tmp = explode(':', $this->application->getPresenter()->name);

        if (count($tmp) >= 2 && $module = strtolower($tmp[0])) {
            return $module;
        } else {
            return $files[0];
        }
    }

}
