# File server installation

## Deploying a test FTP server

A Chef cookbook is available to install a GigaDB test FTP server on a
VirtualBox VM for development work. To deploy this VM, the
`DEPLOY_GIGADB_FTP='true'` environment variable needs to be defined. In
MacOSX, this variable can be declared in your `~/.bash_profile` file.
This will enable the Vagrantfile to instantiate a second VM which
replicates GigaDB's current FTP server when you `source ~/.bash_profile`
and `vagrant up`.

The fileserver VM has an internal IP address: `10.1.1.33`. This can be
used to test the FTP server is working:

```bash
$ ftp 10.1.1.33
Connected to 10.1.1.33.
220 Welcome to the GigaDB FTP service
Name (10.1.1.33:peterli): anonymous
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp> ls
229 Entering Extended Passive Mode (|||56335|).
150 Here comes the directory listing.
-rw-r--r--    1 14       50              6 Feb 07 01:39 foo.txt
226 Directory send OK.
ftp> get foo.txt
local: foo.txt remote: foo.txt
229 Entering Extended Passive Mode (|||61927|).
150 Opening BINARY mode data connection for foo.txt (6 bytes).
100% |***************************************************************************************************************|     6       14.53 KiB/s    00:00 ETA
226 Transfer complete.
6 bytes received in 00:00 (11.31 KiB/s)
ftp> quit
221 Goodbye.
$ more foo.txt 
stuff
```
The anonymous login directory is `/var/ftp/pub`.

Four user drop boxes are also available on the VM. For example, the
user1 drop box can be accessed using `gigadb1` as its password:
as follows:

```bash
$ ftp 10.1.1.33
Connected to 10.1.1.33.
220 Welcome to the GigaDB FTP service
Name (10.1.1.33:peterli): user1
331 Please specify the password.
Password: 
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp> quit
221 Goodbye.
```

To gain SSH access to the VM:

```bash
$ vagrant ssh ftp-server
```

After connecting to the VM by SSH, you can log into the FTP users database:

```bash
$ psql -U gigadb -h localhost -d ftpusers -W
ftpusers=> select * from ftp_users;
```

## Production deployment of the GigaDB FTP server

To install GigaDB on a RedHat-based machine, the FTP server is installed locally
on the same machine where the source code for the service has been downloaded 
on. Start this process by logging into the server:
```bash
# Replace user and server.ip.address
$ ssh user@server.ip.address
```

Install [chef-solo](https://docs.chef.io/ctl_chef_solo.html). This command 
line tool executes chef-client in a way that does not require the Chef server
in order to converge cookbooks. chef-solo uses chef-client’s Chef local mode,
and does not support the functionality present in chef-client/server 
configurations.
```bash
$ wget https://www.opscode.com/chef/install.sh
--2017-02-08 03:41:23--  https://www.opscode.com/chef/install.sh
Resolving www.opscode.com... 54.186.31.111, 54.200.190.77, 54.244.32.246
Connecting to www.opscode.com|54.186.31.111|:443... connected.
HTTP request sent, awaiting response... 200 OK
Length: 20507 (20K) [application/x-sh]
Saving to: “install.sh”

100%[===========================================================================================================================>] 20,507      --.-K/s   in 0s      

2017-02-08 03:41:24 (294 MB/s) - “install.sh” saved [20507/20507]
# Enable install.sh to be executable
$ chmod a+x install.sh
$ sudo ./install.sh -v 12.14.89
el 6 x86_64
Getting information for chef stable  for el...
downloading https://omnitruck-direct.chef.io/stable/chef/metadata?v=&p=el&pv=6&m=x86_64
  to file /tmp/install.sh.1631/metadata.txt
trying wget...
sha1    bf54e7f486c2b0077db62bfa48adecd7110df332
sha256  d97c3a2279366816cfbdb22916d0952b9da1627a1653b42d3ef71022619473e4
url     https://packages.chef.io/files/stable/chef/12.18.31/el/6/chef-12.18.31-1.el6.x86_64.rpm
version 12.18.31
downloaded metadata file looks valid...
downloading https://packages.chef.io/files/stable/chef/12.18.31/el/6/chef-12.18.31-1.el6.x86_64.rpm
  to file /tmp/install.sh.1631/chef-12.18.31-1.el6.x86_64.rpm
trying wget...
Comparing checksum with sha256sum...

WARNING WARNING WARNING WARNING WARNING WARNING WARNING WARNING WARNING

You are installing an omnibus package without a version pin.  If you are installing
on production servers via an automated process this is DANGEROUS and you will
be upgraded without warning on new releases, even to new major releases.
Letting the version float is only appropriate in desktop, test, development or
CI/CD environments.

WARNING WARNING WARNING WARNING WARNING WARNING WARNING WARNING WARNING

Installing chef 
installing with rpm...
warning: /tmp/install.sh.1631/chef-12.18.31-1.el6.x86_64.rpm: Header V4 DSA/SHA1 Signature, key ID 83ef826a: NOKEY
Preparing...                ########################################### [100%]
   1:chef                   ########################################### [100%]
Thank you for installing Chef!
```

Install git:
```bash
$ sudo yum install git
```

Download/clone the gigadb-website github source code repository into
your user directory:
```bash
$ git clone https://github.com/gigascience/gigadb-website.git
```

Change to the new-feature/prod-ftp branch in the gigadb-website and download 
Chef 
cookbooks:
```bash
$ cd gigadb-website
$ git checkout new-feature/prod-ftp
# Download chef cookbooks
$ git submodule init
$ git submodule update
```

Create a /vagrant directory and copy the contents of the
gigadb-website github source code repository into there:
```bash
$ sudo mkdir /vagrant
$ cd /vagrant
$ sudo cp -R $HOME/gigadb-website/* /vagrant
```

Add a production.json file into the `/vagrant/chef/environments`
directory. This file contains a number of variables required by the 
GigaDB website to function. The technical staff at GigaScience can
provide you with a production.json file.

Create a `solo.rb` file in the `/vagrant/chef` directory using
the content below.
```bash
add_formatter :min
checksum_path '/vagrant/chef/checksums'
cookbook_path ['/vagrant/chef/chef-cookbooks','/vagrant/chef/site-cookbooks']
data_bag_path '/home/centos/chef/data_bags'
environment 'production'
environment_path '/vagrant/chef/environments' 
file_backup_path '/vagrant/chef/backup' 
file_cache_path '/vagrant/chef/cache' 
json_attribs nil
lockfile '/vagrant/chef/chef.pid' 
log_level :debug
log_location STDOUT
node_name 'gigadata01.local'
rest_timeout 300
role_path '/vagrant/chef/roles' 
sandbox_path 'path_to_folder'
solo false
syntax_check_cache_path
umask 0022
verbose_logging nil
```

Create a `node.json` file in the `/vagrant/chef/nodes` directory 
which contains the following:
```json
{
  "run_list": [
    "recipe[fileserver]"
  ],
  "environment": "production" 
}
```

Use chef-solo to install the GigaDB website on the server:
```bash
$ sudo chef-solo -c /vagrant/chef/solo.rb -j /vagrant/chef/nodes/node.json
```

To check whether the Chef installation has been successful, try logging into 
the FTP users database:

```bash
$ psql -U gigadb -h localhost -d ftpusers -W
ftpusers=> select * from ftp_users;


The anonymous login directory is `/var/ftp/pub`.

The fileserver VM has an internal IP address: `10.1.1.33`. This can be
used to test the FTP server is working:

```bash
$ ftp 192.168.208.74
Connected to 10.1.1.33.
220 Welcome to the GigaDB FTP service
Name (10.1.1.33:peterli): anonymous
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp> ls
229 Entering Extended Passive Mode (|||56335|).
150 Here comes the directory listing.
-rw-r--r--    1 14       50              6 Feb 07 01:39 foo.txt
226 Directory send OK.
ftp> get foo.txt
local: foo.txt remote: foo.txt
229 Entering Extended Passive Mode (|||61927|).
150 Opening BINARY mode data connection for foo.txt (6 bytes).
100% |***************************************************************************************************************|     6       14.53 KiB/s    00:00 ETA
226 Transfer complete.
6 bytes received in 00:00 (11.31 KiB/s)
ftp> quit
221 Goodbye.
$ more foo.txt 
stuff
```

Four user drop boxes are also available on the VM. For example, the
user1 drop box can be accessed using `gigadb1` as its password:
as follows:

```bash
$ ftp 10.1.1.33
Connected to 10.1.1.33.
220 Welcome to the GigaDB FTP service
Name (10.1.1.33:peterli): user1
331 Please specify the password.
Password: 
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp> quit
221 Goodbye.

