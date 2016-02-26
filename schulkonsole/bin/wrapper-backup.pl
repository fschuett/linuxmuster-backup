#! /usr/bin/perl

=head1 NAME

wrapper-backup.pl - wrapper for writing backup.conf

=head1 SYNOPSIS

 my $id = $userdata{id};
 my $password = 'secret';
 my $app_id = 91001;

 open SCRIPT, '|-', /usr/lib/schulkonsole/bin/wrapper-backup.pl;
 print SCRIPT <<INPUT;
 $id
 $password
 $app_id
 1
 line1
 line2

 INPUT

=head1 DESCRIPTION

=cut

use strict;
use lib '/usr/share/schulkonsole';
use open ':utf8';
use open ':std';
use Schulkonsole::Config;
use Schulkonsole::DB;
use Schulkonsole::Encode;
use Schulkonsole::Error::Error;
use Schulkonsole::Error::FilesError;



my $id = <>;
$id = int($id);
my $password = <>;
chomp $password;

my $userdata = Schulkonsole::DB::verify_password_by_id($id, $password);
exit (  Schulkonsole::Error::Error::WRAPPER_UNAUTHENTICATED_ID)
	unless $userdata;

my $app_id = <>;
($app_id) = $app_id =~ /^(\d+)$/;
exit (  Schulkonsole::Error::Error::WRAPPER_APP_ID_DOES_NOT_EXIST)
	unless defined $app_id;

my $app_name = 'write_backup_conf';
exit (  Schulkonsole::Error::Error::WRAPPER_APP_ID_DOES_NOT_EXIST)
	unless defined $app_name;



my $permissions = Schulkonsole::Config::permissions_apps();
my $groups = Schulkonsole::DB::user_groups(
	$$userdata{uidnumber}, $$userdata{gidnumber}, $$userdata{gid});

my $is_permission_found = 0;
foreach my $group (('ALL', keys %$groups)) {
	if ($$permissions{$group}{$app_name}) {
		$is_permission_found = 1;
		last;
	}
}
exit (  Schulkonsole::Error::Error::WRAPPER_UNAUTHORIZED_ID)
	unless $is_permission_found;


my $opts;
SWITCH: {

=head3 write_files

numeric constant: C<Schulkonsole::Config::WRITEFILESAPP>

=head4 Parameters from standard input

=over

=item file

1 = backup.conf

=back

=cut

$app_id == 91001 and do {
	my $file = <>;
	($file) = $file =~ /^(\d+)$/;
	exit (  Schulkonsole::Error::FilesError::WRAPPER_INVALID_FILENUMBER)
		unless defined $file and $file == 1;

	my $perm;
	my $filename = Schulkonsole::Encode::to_fs(
			'/etc/linuxmuster/backup.conf');
	$perm = 0755 unless -e $filename;

	$< = $>;
	$) = 0;
	$( = $);
	umask(022);

	open FILE, '>', $filename
		or exit(Schulkonsole::Error::Error::WRAPPER_CANNOT_OPEN_FILE);
	flock FILE, 2;
	seek FILE, 0, 0;

	while (<>) {
		print FILE;
	}

	if (defined $perm) {
		chmod $perm, $filename;
	}

	close FILE;

	exit 0;
};

}

exit -2;	# program error

