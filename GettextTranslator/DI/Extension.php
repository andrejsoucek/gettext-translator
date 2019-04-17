<?php
namespace GettextTranslator;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Helpers;

if (!class_exists('Nette\DI\CompilerExtension'))
{
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
}
class Extension extends CompilerExtension
{
	/** @var array */
	private $defaults = array(
		'lang' => 'en',
		'files' => array(),
		'layout' => 'horizontal',
		'height' => 450,
		'scanToFile'=> null,
	);

	public function loadConfiguration()
	{
		$extension = $this->setConfig(array_merge($this->defaults, $this->getConfig()));
		$config = $extension->config;
		$builder = $this->getContainerBuilder();
		$translator = $builder->addDefinition($this->prefix('translator'));
		$translator->setFactory('GettextTranslator\Gettext', array('@session', '@cacheStorage', '@httpResponse'));
		$translator->addSetup('setLang', array($config['lang']));
		$translator->addSetup('setProductionMode', [Helpers::expand('%productionMode%', $this->compiler->getContainerBuilder()->parameters)]);
		foreach ($config['files'] AS $id => $file)
		{
			$translator->addSetup('addFile', array($file, $id));
		}
		$translator->addSetup('setScanToFile', array($config['scanToFile']));
		$translator->addSetup('GettextTranslator\Panel::register', array('@application', '@self', '@session', '@httpRequest', $config['layout'], $config['height']));
	}
}
