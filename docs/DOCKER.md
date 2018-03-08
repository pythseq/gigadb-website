# Installing GigaDB as a multiple Docker container application

## Deploying gigadb-website

GigaDB can be deployed as a multiple container application using Docker. This 
involves using the Docker Compose tool which deploys a number of containers to
run the services required for hosting the GigaDB website and file server. The 
deployment of GigaDB with Docker uses the 
[yii2-laradock](https://github.com/ydatech/yii2-laradock) fork from the
[Laradock](https://github.com/laradock/laradock) Docker PHP development
framework created by [Mahmoud Zalt](https://github.com/Mahmoudz). 

### Procedure

After you have `git clone https://github.com/gigascience/gigadb-website.git`, 
you will have downloaded the `gigadb-website` repository. The next step is to 
download the GigaDB-specific Laradock project into the gigadb-website repo as a 
sub-module:
```bash
# Change directory into repository
$ cd gigadb-website
# Change branch
$ git checkout develop
# Download sub-modules
$ git submodule init
$ git submodule update
```

You should see a `yii2-laradock` directory in your `gigadb-website` repository. 
Now create a docker-compose configuration file in the `yii2-laradock` directory:
```bash
$ cp yii2-laradock/env-gigadb yii2-laradock/.env
```

Chef-Solo is used to create a number of GigaDB source files from templates to 
configure the running of the website.

:exclamation: To do: Docker should generate these website configuration files
so that website config values are kept in yii2-laradock directory! Currently,
configuration values are duplicated in yii2-laradock and chef directories.

The values to configure various variables in these template files come from a 
`docker.json` file located in the `gigadb-website/chef/environments`
directory. This file can be created by copying the `docker.json.sample` 
into a new file called `docker.json`:

```bash
cp chef/environments/docker.json.sample chef/environments/docker.json
```

Vagrant can now be used to spin up an Ubuntu VM with Docker installed and with 
the `gigadb-website` repository folder synchronised at `/vagrant`:
```bash
$ vagrant up
# Log into Ubuntu VM
$ vagrant ssh
```

If you change directory to `/vagrant/yii2-laradock` in the Ubuntu Docker VM, you
can use the [Docker Compose](https://docs.docker.com/compose/) tool to build and 
start the separate containers that can collectively run an instance of GigaDB. 
This tool relies on a `docker-compose.yml` file which specifies what services 
are in the GigaDB Docker application.
```bash
$ cd /vagrant/yii2-laradock
$ docker-compose up -d nginx postgres fileserver-vsftpd
```

If this docker-compose process is successful then the GigaDB website will be 
visible at [http://192.168.42.10]( http://192.168.42.10) on your web browser.

### List containers

All of the project containers in this Dockerised version of GigaDB can be 
listed:

```bash
$ docker ps -a
  CONTAINER ID        IMAGE                              COMMAND                  CREATED             STATUS                         PORTS                                                              NAMES
  61a3e76ca3ee        yii2laradock_nginx                 "nginx"                  About an hour ago   Up About an hour               0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp                           yii2laradock_nginx_1
  247012061b8c        yii2laradock_fileserver-vsftpd     "/usr/sbin/run-vsftp…"   About an hour ago   Up About an hour               0.0.0.0:21->21/tcp, 20/tcp, 0.0.0.0:21100-21103->21100-21103/tcp   yii2laradock_fileserver-vsftpd_1
  22316311df25        yii2laradock_php-fpm               "docker-php-entrypoi…"   About an hour ago   Up About an hour               9000/tcp                                                           yii2laradock_php-fpm_1
  cbee1dde80cb        yii2laradock_workspace             "/sbin/my_init"          About an hour ago   Up About an hour               0.0.0.0:2222->22/tcp                                               yii2laradock_workspace_1
  f4582c841259        yii2laradock_fileserver-postgres   "docker-entrypoint.s…"   About an hour ago   Up About an hour               5432/tcp, 0.0.0.0:5432->5442/tcp                                   yii2laradock_fileserver-postgres_1
  8faf6331aa6f        tianon/true                        "/true"                  About an hour ago   Exited (0) About an hour ago                                                                      yii2laradock_applications_1
  2887afa9e661        yii2laradock_postgres              "docker-entrypoint.s…"   About an hour ago   Up About an hour               0.0.0.0:5400->5432/tcp                                             yii2laradock_postgres_1
```

From the above output, you will see that GigaDB is comprised of a number of 
containers:

| Container  | Function  |
|---|---|
| **yii2laradock_applications_1**  | A small container that has finished running, hence its exit state. Its role is to point to the gigadb-website directory to allow the source code to be used by the other containers.  |
| **yii2laradock_nginx_1**   | Provides a container running the NGINX web server.  |
| **yii2laradock_php-fpm_1**  | A container which runs a FastCGI server for PHP applications. It parses the PHP code and returns a response.  |
| **yii2laradock_postgres_1**   | PostgreSQL container which hosts the GigaDB database that manages the metadata for the data files in GigaDB.  |
| **yii2laradock_workspace_1**  | A container that allows you to run artisan and other command line tools when doing development coding for GigaDB.  |
| **yii2laradock_fileserver-vsftpd_1** | VSFTPD container that runs a FTP server that allows files to be up and downloaded from a server. |
| **yii2laradock_fileserver-postgres_1** | Provides a container that hosts a small database containing the FTP users and login details. |

### Check log for container

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

The PostgreSQL database can be managed using the pgAdmin tool. This container
can be deployed using the docker-compose tool:

```bash
$ docker-compose up -d pgadmin
```

Once the pgadmin container is running, it can be accessed from a browser at 
[http://192.168.42.10:5050](http://192.168.42.10:5050).

You will see a login webpage. Enter `pgadmin4@pgadmin.org` as the email address
and `admin` for the password.

To add your database running on the postgres container, click on `Add New 
Server` and provide a new name, e.g. `gigadb`. Click on the connection tab and
input the hostname/address as `192.168.42.10`. Use `gigadb ` as the username and
`vagrant` as the password to access the gigadb postgres database.

### To log into the VSFTPD server:

```bash
$ docker exec -it yii2laradock_fileserver-vsftpd_1 bash
[root@8e8ffa1d4539 /]# 
```

### To test anonymous FTP file download using wget:

```bash
# Make sure you are logged into the Ubuntu Docker VM
$ vagrant ssh
# Use wget to download file from FTP server container
vagrant@vagrant:~$ wget ftp://172.19.0.4/10.5524/100001_101000/100020/readme.txt
```

### To test anonymous FTP file download using FTP client:

```bash
# Find IP address of VSFTPD container using container ID
$ docker inspect 8e8ffa1d4539
# The IP address is found in the IPAddress attribute of the JSON output
# Test anonymous login using 'ftp' as user name
$ ftp 172.19.0.4
Connected to 172.19.0.4.
220 'Welcome to the GigaDB FTP service'
Name (172.19.0.4:vagrant): ftp
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

```bash
$ ftp 172.19.0.4
Connected to 172.19.0.4
220 (vsFTPd 3.0.2)
Name (172.19.0.2:vagrant): user2
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

### Deleting a container and removing its image:

```bash
$ docker stop yii2laradock_fileserver-vsftpd_1
$ docker rm yii2laradock_fileserver-vsftpd_1
$ docker rmi yii2laradock_fileserver-vsftpd
```
