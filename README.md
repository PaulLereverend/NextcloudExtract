# Extract
Place this app in **nextcloud/apps/**

## Supported

* Zip 
* Rar
* Tar
* Gzip
* 7z
* Deb
* Bzip2

## Requirements

* Rar PHP extension
* p7zip 

## Steps to install 7zip on Linux &raquo; Ubuntu and Fedora or CentOS / RHEL

#### Ubuntu

```bash
pecl -v install rar ## or ## sudo apt-get install unrar
sudo apt-get install p7zip p7zip-full
```

#### CentOS 7.x or Fedora
#### Download and install manually 

```bash
wget https://www.mirrorservice.org/sites/dl.fedoraproject.org/pub/epel/7/x86_64/Packages/p/p7zip-16.02-10.el7.x86_64.rpm
wget https://www.mirrorservice.org/sites/dl.fedoraproject.org/pub/epel/7/x86_64/Packages/p/p7zip-plugins-16.02-10.el7.x86_64.rpm

sudo rpm -U --quiet p7zip-16.02-10.el7.x86_64.rpm
sudo rpm -U --quiet p7zip-plugins-16.02-10.el7.x86_64.rpm
```

#### CentOS 6.7
#### From EPEL
Download and install using Package manager

```bash
cat /etc/*release
sudo yum install -y -q epel-release
sudo rpm -U --quiet http://mirrors.kernel.org/fedora-epel/6/i386/epel-release-6-8.noarch.rpm
sudo rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-EPEL-6
sudo yum repolist
sudo yum install -y -q p7zip p7zip-plugins
```

#### Download and install manually 
In case of connectivity [or any other] issues, please follow these steps to download and install the following packages directly.

```bash
wget https://www.mirrorservice.org/sites/dl.fedoraproject.org/pub/epel/6/x86_64/Packages/p/p7zip-16.02-10.el6.x86_64.rpm
wget https://www.mirrorservice.org/sites/dl.fedoraproject.org/pub/epel/6/x86_64/Packages/p/p7zip-plugins-16.02-10.el6.x86_64.rpm

sudo rpm -U --quiet p7zip-16.02-10.el6.x86_64.rpm
sudo rpm -U --quiet p7zip-plugins-16.02-10.el6.x86_64.rpm
```

## TODO

* Add password support
* Add viewer for archives
* Support nextcloud's encryption module

## Preview

![alt text](https://raw.githubusercontent.com/PaulLereverend/NextcloudExtract/master/img/extract.png)
