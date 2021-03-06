Summary: Amberdms Billing System
Name: amberdms-bs
Version: 2.0.1
Release: 1%{?dist}
License: AGPLv3
URL: http://www.amberdms.com/billing
Group: Applications/Internet
Source0: amberdms-bs-%{version}.tar.bz2

BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch: noarch
BuildRequires: gettext
Requires: httpd, mod_ssl
Requires: php >= 5.1.6, mysql-server, php-mysql, php-soap
Requires: tetex-latex, wkhtmltopdf
Requires: php-pear, php-pear-Mail, php-pear-Mail-Mime
Requires: perl, perl-DBD-MySQL

%description
The Amberdms Billing System is an open source accounting, service billing and time keeping application.

%prep
%setup -q -n amberdms-bs-%{version}

%build


%install
rm -rf $RPM_BUILD_ROOT
mkdir -p -m0755 $RPM_BUILD_ROOT%{_sysconfdir}/amberdms/billing_system/
mkdir -p -m0755 $RPM_BUILD_ROOT%{_datadir}/amberdms/billing_system/

# install application files and resources
cp -pr * $RPM_BUILD_ROOT%{_datadir}/amberdms/billing_system/

# install configuration file
install -m0700 include/sample_config.php $RPM_BUILD_ROOT%{_sysconfdir}/amberdms/billing_system/config.php
ln -s %{_sysconfdir}/amberdms/billing_system/config.php $RPM_BUILD_ROOT%{_datadir}/amberdms/billing_system/include/config-settings.php

# install linking config file
install -m755 include/config.php $RPM_BUILD_ROOT%{_datadir}/amberdms/billing_system/include/config.php

# install the apache configuration file
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/httpd/conf.d
install -m 644 help/resources/amberdms-bs-httpdconfig.conf $RPM_BUILD_ROOT%{_sysconfdir}/httpd/conf.d/amberdms-bs.conf

# install cron configuration
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/cron.d/
install -m 644 help/resources/amberdms-bs-cron $RPM_BUILD_ROOT%{_sysconfdir}/cron.d/amberdms-bs


%post

# install SELinux policies
%if 0%{?el4}
	echo "If you are using/plan to use SELinux Enforcing, please refer to the installation manual for SELinux configuration information"
%endif

%if 0%{?el5}
	echo "Installing SELinux policies"
	/usr/sbin/semodule -i %{_datadir}/amberdms/billing_system/help/resources/selinux_policies/amberdmsbs%{?dist}.pp
%endif

%if 0%{?el6}
	echo "Enabling httpd execmem selinux option"
	setsebool -P httpd_execmem 1
%endif


# Reload apache
echo "Reloading httpd..."
/etc/init.d/httpd reload

# update/install the MySQL DB
if [ $1 == 1 ];
then
	# install - requires manual user MySQL setup
	echo "Run cd %{_datadir}/amberdms/billing_system/help/resources/; ./autoinstall.pl to install the SQL database."
else
	# upgrade - we can do it all automatically! :-)
	echo "Automatically upgrading the MySQL database..."
	%{_datadir}/amberdms/billing_system/help/resources/schema_update.pl --schema=%{_datadir}/amberdms/billing_system/help/schema/ -v
fi


%postun

# check if this is being removed for good, or just so that an
# upgrade can install.
if [ $1 == 0 ];
then
	# uninstall selinux policies
	%if 0%{?el5}
		echo "Removing ABS-specific SELinux policies"
		/usr/sbin/semodule -r amberdmsbs
	%endif

	# user needs to remove DB
	echo "The Amberdms Billing System has been removed, but the MySQL database and user will need to be removed manually."
fi


%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root)
%config %dir %{_sysconfdir}/amberdms
%config %dir %{_sysconfdir}/amberdms/billing_system
%attr(770,root,apache) %config(noreplace) %{_sysconfdir}/amberdms/billing_system/config.php
%attr(660,root,apache) %config(noreplace) %{_sysconfdir}/httpd/conf.d/amberdms-bs.conf
%attr(644,root,root) %config(noreplace) %{_sysconfdir}/cron.d/amberdms-bs
%{_datadir}/amberdms

%changelog
* Wed Jan  1 2014 Jethro Carr <jethro.carr@amberdms.com> 2.0.1-1
- Upgraded to release 2.0.1.
* Sun Jun  9 2013 Jethro Carr <jethro.carr@amberdms.com> 2.0.0-1
- Build of Amberdms Billing System release 2.0.0 (formally 1.5.0)
* Tue Jul 31 2012 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-2.rc.1
- Upgraded to release 1.5.0-2.rc.1, the first release candidate for 1.5.0
* Mon Jul 23 2012 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.10
- Upgraded to release 1.5.0-1.beta.10
* Fri Feb 24 2012 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.9
- Upgraded to release 1.5.0-1.beta.9
* Mon Dec 19 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.8
- Upgraded to release 1.5.0-1.beta.8
* Tue Nov 30 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.7
- Upgraded to release 1.5.0-1.beta.7
* Tue Sep 20 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.6
- Upgraded to release 1.5.0-1.beta.6
* Wed Aug 31 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.5
- Upgraded to release 1.5.0-1.beta.5
* Thu Jul 21 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.4
- Upgraded to release 1.5.0-1.beta.4
* Fri Jun 24 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.3
- Upgraded to release 1.5.0-1.beta.3
* Mon Apr 27 2011 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.2
- Upgraded to release 1.5.0-1.beta.2
* Mon Jun 28 2010 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.beta.1
- Upgraded to release 1.5.0-1.beta.1
* Tue May 04 2010 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.alpha.6
- Upgraded to release 1.5.0-1.alpha.6
* Mon May 03 2010 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.alpha.5
- Upgraded to release 1.5.0-1.alpha.5
* Wed Apr 28 2010 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.alpha.4
- Upgraded to release 1.5.0-1.alpha.4
* Thu Apr 22 2010 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.alpha.3
- Upgraded to release 1.5.0-1.alpha.3
* Sun Mar 28 2010 Jethro Carr <jethro.carr@amberdms.com> 1.5.0-1.alpha.1
- Upgraded to release 1.5.0-1.alpha.1
* Mon Mar 08 2010 Jethro Carr <jethro.carr@amberdms.com> 1.4.1
- Upgraded to release 1.4.1
* Sun Dec 06 2009 Jethro Carr <jethro.carr@amberdms.com> 1.4.0
- Upgraded to release 1.4.0
* Tue Oct 18 2009 Jethro Carr <jethro.carr@amberdms.com> 1.3.0
- Upgraded to release 1.3.0
* Mon Aug 17 2009 Jethro Carr <jethro.carr@amberdms.com> 1.3.0.beta.1
- Upgraded to release 1.3.0.beta.1
* Tue Apr 07 2009 Jethro Carr <jethro.carr@amberdms.com> 1.2.0
- Upgraded to release 1.2.0
* Tue Mar 10 2009 Jethro Carr <jethro.carr@amberdms.com> 1.1.0
- Added automatic MySQL upgrade feature
- Upgrade to release 1.1.0
* Tue Feb 17 2009 Jethro Carr <jethro.carr@amberdms.com> 1.0.0
- Wrote new spec file.

