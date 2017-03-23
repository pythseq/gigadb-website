<?php


$esConfig = json_decode(file_get_contents(dirname(__FILE__).'/es.json'), true);
$dbConfig = json_decode(file_get_contents(dirname(__FILE__).'/db.json'), true);
$pre_config = require(dirname(__FILE__).'/local.php');
Yii::setPathOfAlias('Elastica', realpath(dirname(__FILE__). '/../../Elastica/lib'));
Yii::setPathOfAlias('scholar', realpath(dirname(__FILE__).'/../scripts/scholar.py'));

//Importing beanstalkd client
Yii::setPathOfAlias('Beanstalk',dirname(__FILE__).DIRECTORY_SEPARATOR.'../vendors/beanstalk');


# Location where user images are stored
#Yii::setPathOfAlias('uploadPath', realpath(dirname(__FILE__). '/../../images/uploads'));
#Yii::setPathOfAlias('uploadURL', '/images/uploads/');
#Yii::setPathOfAlias('application.views.process.emails', realpath(dirname(__FILE__).'/../views/process-email'));

// This is the configuration for yiic console application.
// Any writable CConsoleApplication properties can be configured here.
return CMap::mergeArray($pre_config, array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'Music',

    # preloading 'log' component
    'preload'=>array('log'),

    # autoloading model and component classes
    'import'=>array(
        'application.models.*',
        'application.components.*',
        'application.behaviors.*',
        'application.vendors.*',
        'application.helpers.*',
    ),
    # application components
    'components'=>array(
        'log'=>array(
            'class'=>'CLogRouter',
            'routes'=>array(
                array(
                    'class'=>'CFileLogRoute',
                    'levels'=>'error, warning, info, debug',
                    'logFile'=>'console.log',
                ),
            ),
        ),
        'authManager'=>array(
            'class'=>'CDbAuthManager',
            #'defaultRoles'=>array('end_user'),
            'connectionID'=>'db',
        ),
       'db'=>array(
            'class'=>'system.db.CDbConnection',
            'connectionString'=>"pgsql:dbname={$dbConfig['database']};host={$dbConfig['host']}",
            'username'=>$dbConfig['user'],
            'password'=>$dbConfig['password'],
            'charset'=>'utf8',
            'persistent'=>true,
            'enableParamLogging'=>true,
            'schemaCachingDuration'=>30
        ),
       'image'=>array(
            'class'=>'application.extensions.image.CImageComponent',
            # GD or ImageMagick
            'driver'=>'GD',
            # ImageMagick setup path
            #'params'=>array('directory'=>'/opt/local/bin'),
        ),
        'elastic' => array(
            'class' => 'Elastic',
            'host' => $esConfig['host'],
            'port' => $esConfig['port']
        ),
		'beanstalk'=>array(
			'class'=>'application.components.Beanstalk',
			'servers'=>array(
				'server1'=>array(
					'host'=>'localhost',
					'port'=>11300,
					'weight'=>50,
					// array of connections/tubes
					'connections'=>array(),
				),

			),
		),
		'ftp' => array(
			'class' => 'ext.GFtp.GFtpApplicationComponent',
			'connectionString' => 'ftp://anonymous:anonymous@10.1.1.33:21',
			'timeout' => 120,
			'passive' => true
		),
    ),
    # application-level parameters that can be accessed
    # using Yii::app()->params['paramName']
    'params'=>array(

    ),
));
