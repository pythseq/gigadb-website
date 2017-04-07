<?php

class BundleFilesCommand extends CConsoleCommand {


    public function run($args) {

        $queue = "bundle_queue";
        $local_dir = "/tmp/bundles";

        $this->attachBehavior("loggable", new LoggableCommandBehavior() );
        $this->attachBehavior("ftp", new FileTransferBehavior() );

        $this->log("BundleFilesCommand started", pathinfo(__FILE__, PATHINFO_FILENAME)) ;

        try {

            $consumer = Yii::app()->beanstalk->getClient();
            $consumer->connect();
            $consumer->watch('filespackaging');
            $this->log("connected to the job server, waiting for new jobs...", pathinfo(__FILE__, PATHINFO_FILENAME)) ;

            if (false === is_dir($local_dir) ) {
                $workdir_status = mkdir("$local_dir", 0700);
                if (false === $workdir_status) {
                    throw new Exception ("Error creating directory $local_dir");
                }
            }

            $this->log("work directory created..." , pathinfo(__FILE__, PATHINFO_FILENAME)) ;

            while (true) {

                try {

                    $this->log("Reserving next job...", pathinfo(__FILE__, PATHINFO_FILENAME)) ;

                    $job = $consumer->reserve();
                    if (false === $job) {
                        throw new Exception("Error reserving a new job from the job queue");
                    }
                    $result = $consumer->touch($job['id']);

                    if( $result )
                    {

                        $body_array = json_decode($job['body'], true);
                        $bundle = unserialize($body_array['list']);
                        $bid = $body_array['bid'];
                        $dataset_id = $body_array['dataset_id'];

                        $this->log("Got a new job...", pathinfo(__FILE__, PATHINFO_FILENAME)) ;

                        $connectionString = "ftp://anonymous:anonymous@10.1.1.33:21/pub/10.5524";
                        $conn_id = $this->getFtpConnection($connectionString);

                        $this->log("connected to ftp server, ready to download files...", pathinfo(__FILE__, PATHINFO_FILENAME)) ;

                        //create directory for the files
                        $bundle_dir = $bid;
                        $this->log("Creating working directory " . "$local_dir/$bundle_dir" . "...", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                        if (!(is_dir("$local_dir/$bundle_dir")))
                            mkdir("$local_dir/$bundle_dir", 0700);
                        chdir("$local_dir/$bundle_dir");

                        //create a compressed tar archive
                        $tar = new Archive_Tar("$local_dir/bundle_$bundle_dir.tar.gz", "gz");


                        foreach ($bundle as $selection) {

                            $location = $selection["location"];
                            $filename = $selection["filename"];
                            $type = $selection["type"];

                            $location_parts = parse_url($location);

                            $this->log("downloading " . $location_parts['path'] . " -> " . "$local_dir/$bundle_dir/$filename " , pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                            $download_status = false;
                            chdir("$local_dir/$bundle_dir/");

                            if ($type === "Directory") {
                                $download_status = $this->ftp_getdir($conn_id, $location_parts['path'], $dataset_id);
                            }
                            else {
                                $download_status = ftp_get($conn_id,
                                "$local_dir/$bundle_dir/$filename",
                                $location_parts['path'],
                                $this->get_ftp_mode($location_parts['path'])
                            );
                            }

                            if (false === $download_status) {
                                $this->log("Error while downloading" .  $location_parts['path'] . "" , pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                                $fp = fopen("$local_dir/$bundle_dir/$filename.error", 'w');
                                fwrite($fp, "Error while downloading from " . $location_parts['path']. ": \n");
                                fwrite($fp, error_get_last()['message']);
                                fclose($fp);
                                $archive_status = $tar->add(["$local_dir/$bundle_dir/$filename.error"]);
                            }
                            else {
                                if ($type === "Directory") {
                                    $portable_path = str_replace("/pub/10.5524/100001_101000/$dataset_id/","", $location_parts['path']);
                                    $this->log("adding " . "$portable_path" .  " to $local_dir/bundle_$bundle_dir.tar.gz", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                                    $archive_status = $tar->addModify(["$local_dir/$bundle_dir/$portable_path"], "", "$local_dir/$bundle_dir");
                                }
                                else if (pathinfo($location_parts['path'], PATHINFO_DIRNAME) === "/pub/10.5524/100001_101000/$dataset_id") {
                                    $portable_path = "" ;
                                    $this->log("adding " . "$filename" .  " to $local_dir/bundle_$bundle_dir.tar.gz", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                                    $archive_status = $tar->addModify(["$local_dir/$bundle_dir/$filename"], $portable_path, "$local_dir/$bundle_dir/");
                                }
                                else {
                                    $portable_path = str_replace("/pub/10.5524/100001_101000/$dataset_id/","", pathinfo($location_parts['path'], PATHINFO_DIRNAME));
                                    $this->log("adding " . "$portable_path/$filename" .  " to $local_dir/bundle_$bundle_dir.tar.gz", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                                    $archive_status = $tar->addModify(["$local_dir/$bundle_dir/$filename"], $portable_path, "$local_dir/$bundle_dir/");
                                }

                                if (false === $archive_status) {
                                    throw new Exception("Error while:" . "adding " . "$local_dir/$bundle_dir/$filename" .  " to $local_dir/bundle_$bundle_dir.tar.gz");
                                }
                                // else {
                                //     $this->log(var_dump($tar->listcontent()), pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                                // }
                            }
                        }
                        $upload_job = $this->prepare_upload_job("$local_dir/bundle_$bundle_dir.tar.gz",$bid);
                        if($upload_job) {
                            $this->log("Submitted an upload job with id: $upload_job", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                        }
                        else {
                            $this->log("An error occured while submitting an upload job", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                        }

                        $this->log("Job done...(" . $job['id'] . ")", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                        $deletion_status = $consumer->delete($job['id']);
                        if (true === $deletion_status) {
                            $this->log("Job for bundle $bid successfully deleted", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                        }
                        else {
                            $this->log("Failed to delete job for bundle $bid]", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                        }
                        $this->rrmdir("$local_dir/$bundle_dir");

                        ftp_close($conn_id);
                    }
                    else
                    {
                        throw new Exception("Failed touching the newly reserved job");
                    }
                }
                catch(Exception $loopex) {
                    ftp_raw($conn_id, 'NOOP');
                    $this->log("Error while processing job of id " . $job['id'] . ":" . $loopex->getMessage(), pathinfo(__FILE__, PATHINFO_FILENAME)) ;
                    $consumer->bury($job['id'],0);
                    $this->log("The job of id: " . $job['id'] . " has been " . $consumer->statsJob($job['id'])['state'], pathinfo(__FILE__, PATHINFO_FILENAME)) ;

                }


            }

            ftp_close($conn_id);
            $consumer->disconnect();
            $this->log("Closed FTP connection and stopped listenging to the job queue...", pathinfo(__FILE__, PATHINFO_FILENAME)) ;

        } catch (Exception $runex) {
            $this->log("Error while initialising the worker: " . $runex->getMessage(), pathinfo(__FILE__, PATHINFO_FILENAME)) ;
            ftp_close($conn_id);
            $consumer->disconnect();
            $this->log( "BundleFilesCommand stopping", pathinfo(__FILE__, PATHINFO_FILENAME));
            return 1;
        }

        $consumer->disconnect();
        $this->log( "BundleFilesCommand stopping", pathinfo(__FILE__, PATHINFO_FILENAME));
        return 0;
    }


    function prepare_upload_job($file_path, $bid) {
        $client = Yii::app()->beanstalk->getClient();
        $client->useTube('bundleuploading');
        $jobDetails = [
            'application'=>'gigadb-website',
            'file_path'=>$file_path,
            'bid'=>$bid,
            'submission_time'=>date("c"),
        ];

        $jobDetailString = json_encode($jobDetails);

        $ret = $client->put(
            0, // priority
            0, // do not wait, put in immediately
            90, // will run within n seconds
            $jobDetailString // job body
        );

        return $ret;

    }

    function clean_up_files($directory) {
        $files = array_diff(scandir($directory), array('..', '.'));
        foreach ($files as $file) {
            unlink("$directory/$file");
        }
        $rmdir_status  = rmdir($directory);
        if (false === $rmdir_status) {
            $this->log("Failed removing $directory", pathinfo(__FILE__, PATHINFO_FILENAME)) ;
        }

    }

    function rrmdir($dir) {
       if (is_dir($dir)) {
         $objects = scandir($dir);
         foreach ($objects as $object) {
           if ($object != "." && $object != "..") {
             if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
           }
         }
         reset($objects);
         rmdir($dir);
       }
    }

}
