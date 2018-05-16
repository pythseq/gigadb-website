# Running GigaDB as a multiple container Docker application

GigaDB can be deployed as a multiple container application using Docker. This 
involves using the [Docker Compose](https://docs.docker.com/compose/) tool which 
deploys a number of containers to run the services required for hosting the 
GigaDB website and file server. The deployment of GigaDB with Docker uses the 
[yii2-laradock](https://github.com/ydatech/yii2-laradock) fork from the
[Laradock](https://github.com/laradock/laradock) Docker PHP development
framework created by [Mahmoud Zalt](https://github.com/Mahmoudz). 

## Procedure

To begin deploying gigadb-website as Docker application, you will need to set
the `GIGADB_BOX` environment variable to `docker`. This can be declared in your
`~/.bash_profile` (you will then need to `source` this file) or executing the 
command below on your command-line:
```bash
$ export GIGADB_BOX='docker'
```

In your working directory, download the GigaDB source code:
```bash
$ git clone https://github.com/gigascience/gigadb-website.git
```

The next step is to download the GigaDB-specific Laradock project into the 
gigadb-website repo as a sub-module:
```bash
# Change directory into repository
$ cd gigadb-website
# Change to docker-rija-ftp-table branch
$ git checkout docker-rija-ftp-table
# Download sub-modules
$ git submodule init
$ git submodule update
```

You should see a `yii2-laradock` directory in your `gigadb-website` repository. 
Now create a docker-compose configuration file in the `yii2-laradock` directory:
```bash
$ cp yii2-laradock/env-gigadb yii2-laradock/.env
```

Vagrant can now be used to spin up an Ubuntu VM with Docker installed and with 
the `gigadb-website` repository folder synchronised at `/vagrant`:
```bash
$ vagrant up
# Log into Ubuntu VM
$ vagrant ssh
```

As part of the VM creation process, `yii2-laradock/generate.config.sh` will be
executed to generate the config files in `protected/config/` required by 
gigadb-website and download the Yii version 1 framework into `/opt/yii-1.1.16`
in the Ubuntu VM for use by the containers.

If you change directory to `/vagrant/yii2-laradock` in the Ubuntu Docker VM, you
can use the [Docker Compose](https://docs.docker.com/compose/) tool to build and 
start the separate containers that can collectively run an instance of GigaDB. 
This tool relies on a `docker-compose.yml` file which configures the containers 
in the GigaDB Docker application. For now, we will start up 3 containers: the 
nginx web server, the postgres RDBMS which stores information about the datasets
and vsftpd which will provide the FTP server that allows download access to 
dataset files listed in the GigaDB website:
```bash
$ cd /vagrant/yii2-laradock
$ docker-compose up -d nginx postgres vsftpd
```

If the docker-compose process is successful then the GigaDB website will be 
displayed at [http://192.168.42.10]( http://192.168.42.10) on your web browser.

## Listing containers

All of the containers in this Dockerised version of GigaDB can be listed:
```bash
$ docker ps -a
  CONTAINER ID        IMAGE                    COMMAND                  CREATED              STATUS                          PORTS                                                                                      NAMES
  92cfb34752e4        yii2laradock_nginx       "nginx"                  About a minute ago   Up About a minute               0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp                                                   website-nginx
  b096d4a8f356        yii2laradock_php-fpm     "docker-php-entrypoi…"   About a minute ago   Up About a minute               9000/tcp                                                                                   website-php-fpm
  ccb2bc01549b        yii2laradock_vsftpd      "/usr/sbin/run-vsftp…"   5 minutes ago        Up 5 minutes                    0.0.0.0:21->21/tcp, 0.0.0.0:9111->9111/tcp, 20/tcp, 0.0.0.0:21100-21103->21100-21103/tcp   vsftpd
  eee505d6196e        yii2laradock_workspace   "/sbin/my_init"          5 minutes ago        Up 5 minutes                    0.0.0.0:2222->22/tcp                                                                       workspace
  085f87b6d842        yii2laradock_postgres    "docker-entrypoint.s…"   5 minutes ago        Up 5 minutes                    0.0.0.0:5432->5432/tcp                                                                     postgres
  0dc2e94fcd30        tianon/true              "/true"                  5 minutes ago        Exited (0) About a minute ago
```

From the above output, you will see that GigaDB is comprised of a number of 
containers:

| Container  | Function  |
|---|---|
| **gigadb-app**  | A small container that has finished running, hence its exit state. Its role is to point to the gigadb-website directory to allow the source code to be used by the other containers.  |
| **website-nginx**   | Provides a container running the NGINX web server.  |
| **website-php-fpm**  | A container which runs a FastCGI server for PHP applications. It parses the PHP code and returns a response.  |
| **workspace**  | A container that allows you to run artisan and other command line tools when doing development coding for GigaDB.  |
| **vsftpd** | VSFTPD container that runs a FTP server that allows files to be up and downloaded from a server. |
| **postgres** | Provides a container that hosts a database containing the FTP users and login details, and the GigaDB database containing the metadata for data files. |

### VSFTPD container

The main purpose of the VSFTPD container to run VSFTPD, the FTP server that
provides access to files listed in the GigaDB website. The VSFTPD service is
under the control of [Supervisor](http://supervisord.org), a  Python-based 
process manager that allows its users to monitor and control a number of 
processes on UNIX-like operating systems. Supervisord is its daemon which is 
configured with a config file in INI config format.

The following commands can be used to check the status of supervisor and 
start/stop the process:
```bash
# Log into VSFTPD server
$ docker exec -it vsftpd bash
# Check status
$ supervisorctl status
  generatepreview                  RUNNING   pid 26, uptime 0:04:45
  vsftpd                           RUNNING   pid 25, uptime 0:04:45
```
  
You can see that vsftpd container is running supervisor which is managing two
processes: vsftpd and generatepreview. This latter process is executed when it 
receives a signal that the user has clicked on a preview icon for a dataset file
in the GigaDB website. A function
(`protected/commands/GeneratePreviewCommand.php`) is then executed to create a 
preview file which is sent to the S3 bucket.


## Useful Docker commands

### Check log for a container
```bash
$ docker-compose logs <name of container>
```

### Build Docker image
```bash
$ docker-compose build <name of container>
```

### Delete all Docker containers
```bash
$ docker-compose down -v
```

### Log into a container
```bash
$ docker exec -it <container name> bash
```

For example, to log into the VSFTPD container:
```bash
$ docker exec -it vsftpd bash
[root@8e8ffa1d4539 /]# 
```
### Deleting a container and removing its image:

```bash
$ docker stop vsftpd
$ docker rm vsftpd
$ docker rmi yii2laradock_vsftpd
```

### Host access to Docker on Ubuntu VM

You can access the Docker daemon that is running on the Ubuntu VM directly from
your (host) computer without logging into the VM. For example, you can execute
the following command when the Ubuntu VM is deployed:

```bash
$ docker -H tcp://0.0.0.0:9172 version
Client:
 Version:      17.05.0-ce
 API version:  1.29
 Go version:   go1.9.2
 Git commit:   89658be
 Built:        
 OS/Arch:      darwin/amd64

Server:
 Version:      17.12.0-ce
 API version:  1.35 (minimum version 1.12)
 Go version:   go1.9.2
 Git commit:   c97c6d6
 Built:        Wed Dec 27 20:09:53 2017
 OS/Arch:      linux/amd64
 Experimental: false
```

### Database administration using pgAdmin

The PostgreSQL database can be managed using the `pgAdmin` tool. This container
can be deployed using the docker-compose tool:
```bash
$ docker-compose up -d pgadmin
```

Once the `pgadmin` container is running, it can be accessed from a browser at 
[http://192.168.42.10:5050](http://192.168.42.10:5050).

You will see a login webpage. Enter `pgadmin4@pgadmin.org` as the email address
and `admin` for the password.

To add your database running on the postgres container, click on `Add New 
Server` and provide a new name, e.g. `gigadb`. Click on the connection tab and
input the hostname/address as `192.168.42.10`. Use `gigadb` as the username and
`vagrant` as the password to access the gigadb postgres database.


### Test anonymous FTP file download using wget

The VSFTPD server running in the vsftpd container can be tested by downloading
files from the VSFTPD FTP server:
```bash
# Make sure you are logged into the Ubuntu Docker VM
$ vagrant ssh
# Check IP address for vsftpd docker container
$ docker inspect vsftpd | grep IPAddress
            "SecondaryIPAddresses": null,
            "IPAddress": "",
                    "IPAddress": "172.20.0.4",
# Use the 172.20.0.4 IP address in wget to download file
vagrant@vagrant:~$ wget ftp://172.20.0.4/pub/10.5524/100001_101000/100020/readme.txt
--2018-04-24 02:30:10--  ftp://172.20.0.4/pub/10.5524/100001_101000/100020/readme.txt
           => ‘readme.txt’
Connecting to 172.20.0.4:21... connected.
Logging in as anonymous ... Logged in!
==> SYST ... done.    ==> PWD ... done.
==> TYPE I ... done.  ==> CWD (1) /pub/10.5524/100001_101000/100020 ... done.
==> SIZE readme.txt ... 526
==> PASV ... done.    ==> RETR readme.txt ... done.
Length: 526 (unauthoritative)

readme.txt                                                    100%[================================================================================================================================================>]     526  --.-KB/s    in 0s      

2018-04-24 02:30:10 (86.4 MB/s) - ‘readme.txt’ saved [526]
```

### Test anonymous FTP file download using FTP client

You can also use the ftp client on the Ubuntu VM to download files:
```bash
# Find IP address of VSFTPD container using container ID
$ docker inspect vsftpd | grep IPAddress
            "SecondaryIPAddresses": null,
            "IPAddress": "",
                    "IPAddress": "172.20.0.4",
# The IP address is found in the IPAddress attribute of the JSON output
# Test anonymous login using 'ftp' as user name
$ ftp 172.20.0.4
Connected to 172.20.0.4.
220 'Welcome to the GigaDB FTP service'
Name (172.20.0.4:vagrant): ftp
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp> ls
200 PORT command successful. Consider using PASV.
150 Here comes the directory listing.
-rw-r--r--    1 ftp      ftp            17 Mar 05 02:47 test.txt
226 Directory send OK.
ftp> get test.txt
local: test.txt remote: test.txt
200 PORT command successful. Consider using PASV.
150 Opening BINARY mode data connection for test.txt (17 bytes).
226 Transfer complete.
17 bytes received in 0.00 secs (240.6024 kB/s)
ftp> exit
221 Goodbye.
```

### Test FTP file upload by user2:

There are a number of pre-configured ftp user accounts which can be used to test
file upload into the VSFTPD server.
```bash
$ ftp 172.20.0.4
Connected to 172.20.0.4
220 (vsFTPd 3.0.2)
Name (172.20.0.2:vagrant): user2
331 Please specify the password.
# Use 'gigadb2' as password
Password:
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp> put test_file
local: test_file remote: test_file
200 PORT command successful. Consider using PASV.
150 Ok to send data.
226 Transfer complete.
12 bytes sent in 0.00 secs (142.9116 kB/s)
ftp> ls
200 PORT command successful. Consider using PASV.
150 Here comes the directory listing.
-rw-------    1 1000     1000           12 Mar 07 03:28 test_file
226 Directory send OK.
ftp> 
```

## File preview functionality in GigaDB

The file preview functionality in GigaDB can be tested using Docker by
deploying another 3 containers which each run the following services:

### Redis

This is used by the file preview functionality to track worker stage, status and 
preview URL between the frontend website and the backend VSFTPD server.

### Beanstalk

Beanstalk is a job queue system. When a user clicks on a file preview icon on a 
GigaDB dataset webpage, the website application creates and forwards software 
tasks to background worker scripts in the FTP server.

### Modular file renderer

The modular file renderer (MFR) is a python web application that renders (as 
HTML and PDF) the content of files stored in a remote server in an iframe. MFR 
does not support FTP so it fetches a file via HTTP from S3 and renders this 
file. Support for a particular file format has to to be coded but lots of 
formats are already supported, e.g. images, video, tabular and scientific 
formats. Until supported, this means that formats such as FASTA/FASTQ are not 
rendered by MFR. These files might be supported by MFR if it is annotated as a 
plain/text type.

### Supervisor

The supervisor service runs on the VSFTPD container. When it receives a message
that the user has clicked on a preview icon on a GigaDB dataset web page, it 
executes a function to create a preview file which is sent to the S3 bucket.

The following commands can be used to check the status of supervisor and 
start/stop the process:
```bash
$ /etc/init.d/supervisord status
$ /etc/init.d/supervisord stop
$ /etc/init.d/supervisord start
```

A log file detailing the creation of preview file is available at 
`/var/log/generatepreview.log`.

Redis, beanstalk and the MFR work together as shown in the diagram below:

![Image of file preview architecture](https://drive.google.com/uc?export=view&id=1rbKrm7UjaIKaZe_Zzi8FJDUt1vTXJ87H)

## Container configuration

The 3 extra containers need configuration which is provided in the `.env`
file that was created earlier by copying `env-gigadb`. What is required is to
specifically let the gigadb-website application know the location of Redis, 
Beanstalk and MFR services. This can be provided by the name of the containers
that each of the services reside in since Docker can be resolve them by 
container name. The configuration for Redis, Beanstalk and MFR already in 
`env-gigadb` will work out of the box. Specifically, below are the required 
configurations:

To use the FTP server in the VSFTPD container, use the following values:
```
FTP_CONNECTION_URL=ftp://anonymous:anonymous@vsftpd:21
```

To configure MFR for testing purpose, there is no need to change this value.
Later on in the configuration process you will need to supply me with the bucket
name created so I can whitelist them on this server.
```
PREVIEW_SERVER_HOST=mfr:7778
```

The Redis and Beanstalk services can be resolved by providing their container 
names which used by the `config.php` and `console.php` files in the 
`protected/config` directory.
```
REDIS_SERVER_HOST=redis
BEANSTALK_SERVER_HOST=beanstalkd
```

Configuration that **IS** required is your AWS credentials in order to create 
the S3 bucket for storing preview files. In addition, a value for the 
AWS_S3_BUCKET_FOR_FILE_PREVIEWS is required. This is where the preview files are
uploaded before they are shown in a preview pane (directly or indirectly through
the MFR) to the web visitors:
```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_BUCKET_FOR_FILE_PREVIEWS=
AWS_DEFAULT_REGION=ap-southeast-1
```

## Procedure for testing file preview functionality

Start the Ubuntu Docker VM by ensuring environment variable `GIGADB_BOX=docker` 
and execute:
```
$ vagrant up
```

Boot up all the required containers:
```bash
$ vagrant ssh
vagrant@vagrant:~$ cd /vagrant/yii2-laradock
vagrant@vagrant:~$ docker-compose up -d nginx postgres vsftpd redis beanstalkd modular-file-renderer 
```

You can check whether all the containers have sucessfully started:
```bash 
vagrant@vagrant:/vagrant/yii2-laradock$ docker ps -a
CONTAINER ID        IMAGE                                COMMAND                  CREATED             STATUS                      PORTS                                                                                      NAMES
baa91fb31fbb        yii2laradock_beanstalkd              "/usr/bin/beanstalkd"    21 minutes ago      Up 21 minutes               0.0.0.0:11300->11300/tcp                                                                   beanstalkd
6a7ec53b7867        yii2laradock_nginx                   "nginx"                  21 minutes ago      Up 21 minutes               0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp                                                   website-nginx
b58350a35b30        yii2laradock_vsftpd                  "/usr/sbin/run-vsftp…"   21 minutes ago      Up 21 minutes               0.0.0.0:21->21/tcp, 0.0.0.0:9111->9111/tcp, 20/tcp, 0.0.0.0:21100-21103->21100-21103/tcp   vsftpd
5ff21efc914b        yii2laradock_php-fpm                 "docker-php-entrypoi…"   21 minutes ago      Up 21 minutes               9000/tcp                                                                                   website-php-fpm
ea173548f360        yii2laradock_workspace               "/sbin/my_init"          21 minutes ago      Up 21 minutes               0.0.0.0:2222->22/tcp                                                                       workspace
46a5e538ac19        yii2laradock_modular-file-renderer   "tail -f /dev/null"      21 minutes ago      Up 21 minutes               0.0.0.0:7778->7778/tcp                                                                     mfr_server
ac042f7b6155        yii2laradock_postgres                "docker-entrypoint.s…"   21 minutes ago      Up 21 minutes               0.0.0.0:5432->5432/tcp                                                                     postgres
dab34d348c09        tianon/true                          "/true"                  21 minutes ago      Exited (0) 21 minutes ago                                                                                              gigadb-app
da1ea6b85c93        yii2laradock_redis                   "docker-entrypoint.s…"   21 minutes ago      Up 21 minutes               0.0.0.0:6379->6379/tcp                                                                     redis
```

Only the `gigadb-app` container should show up as having `Exited`.

Now create the S3 file preview bucket using the vsftpd container:
```bash
# Log into the vsftpd container
vagrant@vagrant:~$ docker exec -it vsftpd bash
$ /vagrant/protected/yiic createbucket --fromconfig
```

If you then go to your AWS S3 Management Console webpage, you should see a 
bucket with the name specified by the variable AWS_S3_BUCKET_FOR_FILE_PREVIEWS. 

To delete a bucket and all its content, use the following command:
```bash
$ /vagrant/protected/yiic clearbucket -b=<bucket name> --delete=yes
```

The MFR whitelists URLs that are allowed to have a preview generated. A test
version of MFR has been installed using Docker Compose and this has been 
configured from within the docker-compose.yml file to allow URLs from the 
AWS_S3_BUCKET_FOR_FILE_PREVIEWS to be previewed from it.

If there are no errors with the docker-compose up process then the GigaDB 
website will be visble from [http://192.168.42.10](http://192.168.42.10) from a 
web browser on your host computer. If you then select a dataset such as
[http://192.168.42.10/dataset/100117](http://192.168.42.10/dataset/100117) and 
click on the preview icon for `contributors.txt` then a preview file should
appear in the S3 bucket. Clicking the preview icon a second time should allow 
the preview to be displayed on the dataset web page.

## File Table View

Viewing the blueberry dataset at 
[http://192.168.42.10/dataset/100117](http://192.168.42.10/dataset/100117) will 
allow you to test the file table view functionality. If you look at the 
`AltSplicing` entry, you will notice that it has been marked up with the
`Directory` Data Type. Clicking on `AltSplicing` will allow you to see the
contents of the `AltSplicing` directory.






