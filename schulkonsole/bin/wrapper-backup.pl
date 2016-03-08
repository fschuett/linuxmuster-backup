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
use Schulkonsole::Encode;
use Schulkonsole::Wrapper;
use Schulkonsole::Error::Error;
use Schulkonsole::Error::FilesError;

my $userdata = Schulkonsole::Wrapper::wrapper_authenticate();

my $_id_apps = {
    91001 => 'write_backup_conf',
};

my $app_id = Schulkonsole::Wrapper::wrapper_authorize($userdata, $_id_apps);

my $opts;
SWITCH: {
    $app_id == 91001 and do {
	write_files();
	last SWITCH;
    };
};

exit -2;	# program error

=head3 write_files

numeric constant: C<Schulkonsole::Config::WRITEFILESAPP>

=head4 Parameters from standard input

=over

=item file

1 = backup.conf

=back

=cut

sub write_files {
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
}

