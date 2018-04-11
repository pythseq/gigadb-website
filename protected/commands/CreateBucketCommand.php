<?php

spl_autoload_unregister(array('YiiBase', 'autoload'));
require_once dirname(__FILE__). '/../vendors/aws/aws-autoloader.php';
spl_autoload_register(array('YiiBase', 'autoload'));


class CreateBucketCommand extends CConsoleCommand {
  public function run($args) {

    $this->attachBehavior("commandline", new CommandLineBehavior()) ;

    $this->parseArguments($args,array("h" => "help",  "p" => "preview", "a" =>"all", "c" => "fromconfig"));
    $this->setHelpMessage(array(
      "Usage:",
      "/vagrant/protected/yiic createbucket -h|--help",
      "/vagrant/protected/yiic createbucket -p|--preview=<bucket name>",
      "/vagrant/protected/yiic createbucket -a|--all=<bucket name>",
      "/vagrant/protected/yiic createbucket -c|--fromconfig"
    ));


    if (0 === $this->optionsCount()) {
        $this->printHelpMessage("No arguments passed") ;
        return 1;
    }
    else if( $this->getOption('fromconfig') ) {
        $preview_bucket = Yii::app()->aws->preview_bucket ;
    }
    else if ( $this->getOption('all') ) {
        $preview_bucket = $this->getOption('all') ;
    }
    else if ( $this->getOption('preview')) {
        $preview_bucket = $this->getOption('preview') ;
    }
    else {
        $this->printHelpMessage("Incorrect arguments") ;
        return 1;
    }

    $s3 = Yii::app()->aws->getS3Instance();

    if ( $preview_bucket ) {
        echo "Creating bucket for preview functionality: " . $preview_bucket . PHP_EOL ;
        $s3->createBucket(array('Bucket' => $preview_bucket));
        if (true == $s3->doesBucketExist($preview_bucket)) {
            return 0;
        }
        else {
            return 1;
        }
    }

    return 0 ;
  }
}

?>
