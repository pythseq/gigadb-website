# FTP directories, multi-file download and file preview functionalities

This page documents the set up and use of the following functionalities:

* FTP directories [#63](https://github.com/gigascience/gigadb-website/issues/63)
* Multi-download [#67](https://github.com/gigascience/gigadb-website/issues/67)
* File preview [#66](https://github.com/gigascience/gigadb-website/issues/66)

## [System Architecture](https://gist.githubusercontent.com/rija/f6fa3cfeda0f0a0da4c6f08326bbe6f7/raw/d882eddfb1028b07eaabbc6a5b264af78890921a/gistfile1.txt)

![System architecture](https://drive.google.com/uc?id=1Jlnj3dTB_E8s3c-NlxRvJC-RlDsuNiGs)

## Set up procedure

### Source code

Check out the ftp-table branch from GitHub:
```
$ git clone https://github.com/rija/gigadb-website
$ cd gigadb-website
$ git checkout -b ftp-table origin/ftp-table
$ cd chef
$ git submodule init
$ git submodule update
```

### Configuration of gigadb-website 

Copy `development.json.sample` to `development.json`. Review or change the 
information in the "ftp", "mfr", "aws", and "supervisor" blocks:


#### FTP configuration

To use the FTP server on the ftp-server vagrant box, use the following values:
```
"ftp": {
"connection_url": "ftp://anonymous:anonymous@10.1.1.33:21",
"host": "10.1.1.33"
},
```

Otherwise to connect to the live ftp server, use:
```
"ftp": {
  "connection_url": "ftp://anonymous:anonymous@penguin.genomics.cn:21"
  "host": "penguin.genomics.cn"
},
```

The ftp-server vagrant box needs users information to be filled in in the 
development.json file:
```
"user1": "user1",
"user1_name": "user one",
"user1_public_key": "",
"user1_group": "gigadb-admin",
"user2": "user2",
"user2_name": "user two",
"user2_public_key": "",
"user2_group": "gigadb-admin",
"user3": "user3",
"user3_name": "user three",
"user3_public_key": "",
"user3_group": "gigadb-admin",
"admin_user": "admin",
"admin_user_name": "admin user",
"admin_user_public_key": "",
"admin_user_group": "gigadb-admin",

```

#### MFR

MFR is a python web application that renders (as HTML and PDF) the content of 
files stored in a remote server in an iframe. MFR does not support FTP so it 
fetches a file via HTTP from S3 and renders this file. Support for a particular 
file format has to to be coded but lots of formats supported, e.g. images, 
video, tabular and scientific formats. Until supported, this means that formats 
such as FASTA/FASTQ are not rendered by MFR. These files might be supported by 
MFR if it is annotated as a plain/text type.

To configure MFR, you need to indicate the URL of the preview server from Center
For Open Science: Modular-File-Renderer a.k.a MFR. The default value is of a 
remote test server. For testing purpose, there is no need to change this value.
Later on in the configuration process you will need to supply me with the bucket
name created so I can whitelist them on this server.
```
  "mfr": {
    "preview_server": "128.199.125.190:7778"
  },
```

#### AWS

Firstly, add the Access key and Secret ID of an AWS user that can fully manage 
S3 resources:
```
"aws": {
  "aws_access_key_id": "DUMMYXXXX",
  "aws_secret_access_key": "DUMMYxxxxxxxxxxxxxxxxx",
  "aws_default_region": "ap-southeast-1",
  "aws_security_groups": "",
  "s3_bucket_for_file_previews": "<unique name for preview bucket"
},
```

The __s3_bucket_for_file_previews__ bucket is where the preview files are 
uploaded before they are shown in a preview pane (directly or indirectly through 
MFR) to the web visitors.


#### Supervisor

The new functionalities will work without changing the default values for this 
block. However, on production, it's recommended to increase the value of the 
`numprocs` key to greater than one.

The supervisor service runs on the FTP server. When it receives a message that
the user has clicked on a preview icon, it executes a function to create a
preview file which is sent to the S3 bucket.

The following commands can be used to check the status of supervisor and 
start/stop the process:
```bash
$ /etc/init.d/supervisord status
$ /etc/init.d/supervisord stop
$ /etc/init.d/supervisord start
```

A log file detailing the creation of preview file is available at:

/var/log/generatepreview.log

### Run and provision vagrant images

Vagrantfile now configures **3 machines**. Make sure the following environment 
variables are set:
```
DEPLOY_GIGADB_FTP=true
DEPLOY_GIGADB_QUEUES=true
GIGADB_BOX=centos
```

If running on Mac OS X, and you want to connect to the ftp-server vagrant box, 
the following would help too:
```
GIGADB_ON_MACOSX=true
```

Then run:
```
$ vagrant up
```

### Create required S3 buckets

```
$ vagrant ssh gigadb-website
$ /vagrant/protected/yiic createbucket --fromconfig
```

The above command will create the one bucket with the name configured in section 
[1.3].

To delete a bucket and all its content, use the following command:
```
$ /vagrant/protected/yiic clearbucket -b=<bucket name> --delete=yes
```

### Authorising S3 URLs for the preview bucket to MFR

MFR whitelists URLs that are allowed to have a preview generated. So if you've 
configured the "mfr" block of `development.json` with the default MFR test 
server, please notify Rija the bucket name you've created for preview so he can 
add it to the list of allowed domains on the test server.

Alternatively, if you have Docker installed (MFR uses Docker for dev/test), you 
can run the following commands to deploy your own instance of MFR with your own 
allowed domains:
```bash
$ git clone https://github.com/rija/modular-file-renderer
$ cd modular-file-renderer
$ git checkout -b circus-dockerfile origin/circus-dockerfile
```

On my MacPro 2010 desktop, running Docker applications requires Kitematic which
is available from  Docker Toolbox. Docker for Mac is not compatible with old Mac
computers. Starting the Kitematic software will boot up a Kitematic Docker VM
which can then be used to run the MFR container. Next, use the 
modular-file-renderer to build it Docker image:
```bash
$ eval "$(docker-machine env default)"
$ docker build -t="mfr" .
```

Run the image as a container:
```bash
$ docker run -d -p 7778:7778 \
    -e SERVER_CONFIG_ADDRESS=0.0.0.0 \
    -e SERVER_CONFIG_PROVIDER_NAME=http \
    -e SERVER_CONFIG_ALLOWED_PROVIDER_DOMAINS=http://www.example.com/  \
    --name mfr_server \
    mfr

```

`http://www.example.com/` should be replaced by the s3 url:
```
https://<your preview bucket name>.s3.amazonaws.com
```

Then in the `development.json`, use __192.168.99.100:7778__ in the "mfr" block 
(replace 192.168.99.100 by your docker machine IP address).

### Testing with the supplied sample data

All the new functionalities can be tested by navigating to the dataset view 
below using the files shown in the tree further down:

* http://127.0.0.1:9170/dataset/view/id/100112/
* http://127.0.0.1:9170/dataset/view/id/100117/
* http://127.0.0.1:9170/dataset/view/id/100159/
* http://127.0.0.1:9170/dataset/view/id/100179/
* http://127.0.0.1:9170/dataset/view/id/100258/
* http://127.0.0.1:9170/dataset/view/id/100104/

It's possible to navigate to the other datasets on the database, but the new 
functionalities won't be working on them if using ftp-server vagrant box. 
However, old functionalities should still be working on those.

__Note__: a production-like database dump may need to be loaded to access the 
example datasets above.

Dataset files available on the ftp-server vagrant box:
```
/var/ftp/pub/10.5524/100001_101000/
├── 100104
│   ├── Annotation
│   │   ├── functional_annotation
│   │   │   └── Mongolia_Human.function.statistics.xls
│   │   └── gene_annotation
│   │       └── Mongolia_Human.gene.gff
│   ├── Mongolia_genome.jpg
│   ├── Mongolian_Genome_novel_seq.fa (page 2)
│   └── Variation
│       └── NovelSeq
│           └── Mongolian_Genome_novel_seq.fa
├── 100112
│   ├── Data_Access_Agreement_PGDPGS.doc
│   └── Data_Application_form_PGDPGS.docx
├── 100117
│   ├── AltSplicing
│   │   ├── AltSplicing.Rproj
│   │   ├── ASClustering.html
│   │   ├── ASClustering.Rmd
│   │   ├── BlueberryAltsplice.Rmd
│   │   ├── data
│   │   │   └── AltSplicing_blueberry.f5.ASwiRI.txt.gz
│   │   ├── GeneByGeneAnalysis
│   │   │   └── CUFF.12644
│   │   │       ├── AllSampleTypesExonSkipping.png
│   │   │       └── BlastxScaffold00205-1668-letters.pdf
│   │   └── results
│   │       ├── bberry_altsplice.15.tsv
│   │       └── S.tsv.gz
│   ├── ComparingReplicates
│   │   ├── ComparingReplicates.pptx
│   │   ├── cup
│   │   │   ├── blueberrycupall.png
│   │   │   ├── coord26.png
│   │   │   ├── coord28.png
│   │   │   ├── coord45.png
│   │   │   ├── coord75.png
│   │   │   ├── coord77.png
│   │   │   └── coord98.png
│   │   ├── green
│   │   │   ├── blueberrygreenall.png
│   │   │   ├── coord100.png
│   │   │   ├── coord264.png
│   │   │   ├── coord288.png
│   │   │   ├── coord36.png
│   │   │   ├── coord42.png
│   │   │   ├── coord45.png
│   │   │   ├── coord61.png
│   │   │   ├── coord64.png
│   │   │   └── coord78.png
│   │   ├── pads
│   │   │   ├── blueberrypadall.png
│   │   │   ├── coord45.png
│   │   │   ├── coord62.png
│   │   │   ├── coord70.png
│   │   │   ├── coord90.png
│   │   │   ├── coord97.png
│   │   │   └── padalldepthgraph.png
│   │   ├── pink
│   │   │   ├── blueberrypinkall.png
│   │   │   ├── coord14.png
│   │   │   ├── coord40.png
│   │   │   ├── coord74.png
│   │   │   └── coord90.png
│   │   └── ripe
│   │       ├── coord415.png
│   │       ├── coord46.png
│   │       ├── coord56.png
│   │       ├── coord635.png
│   │       ├── coord70.png
│   │       ├── coord79.png
│   │       ├── coord92.png
│   │       └── igbblueberryripereps.png
│   ├── contributors.txt
│   ├── README.md
│   └── V_corymbosum_scaffold_May_2013.fa.gz (page 3)
├── 100159
│   └── S_typhi_H58
│       └── STH58links11.pairing_distribution.csv (page 9)
├── 100179
│   └── chr1-7_opmap.xml
└── 100258
    ├── readme.txt
    └── tBLASTx_all
        └── SBdb_CAq.txt  (page 3)
```
