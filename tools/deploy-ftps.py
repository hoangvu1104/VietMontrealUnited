"""Deploy the application allowlist over explicit FTPS; preserve server data."""
import argparse
import ftplib
import json
import os
from pathlib import Path, PurePosixPath
import ssl

ROOT = Path(__file__).resolve().parent.parent


def deployment_files():
    files = json.loads((ROOT / 'tools/deploy-manifest.json').read_text())['public']
    for relative in files:
        path = PurePosixPath(relative)
        allowed = relative in {'.htaccess', 'index.php', 'config/database.php',
                               'config/match-venue.php', 'public/.htaccess',
                               'public/index.php', 'public/uploads/.htaccess'}
        allowed = allowed or relative.startswith(('app/', 'public/assets/'))
        if not allowed or path.is_absolute() or '..' in path.parts or '\\' in relative:
            raise ValueError(f'Invalid deployment path: {relative}')
        source = ROOT / relative
        if not source.is_file() or source.is_symlink():
            raise ValueError(f'Missing or invalid source: {relative}')
    return files


def deploy():
    parser = argparse.ArgumentParser()
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()
    files = deployment_files()
    if args.dry_run:
        print(f'Dry run: {len(files)} allowlisted files; no connection or upload.')
        return
    host = os.environ['FTP_HOST']
    port = int(os.environ.get('FTP_PORT', '21'))
    remote_root = os.environ.get('FTP_ROOT', '/')
    if not remote_root.startswith('/') or '..' in PurePosixPath(remote_root).parts:
        raise ValueError('FTP_ROOT must be an absolute path without parent traversal.')
    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=60) as ftp:
        ftp.connect(host, port)
        ftp.login(os.environ['FTP_USER'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd(remote_root)
        # Fail before writing if this account does not point to the existing VMU app.
        for directory in ('app', 'config', 'public'):
            ftp.cwd(directory)
            ftp.cwd('..')
        ftp.voidcmd('TYPE I')
        ftp.size('config/database.local.php')
        created = set()
        for relative in files:
            parent = str(PurePosixPath(relative).parent)
            if parent != '.' and parent not in created:
                current = ''
                for part in PurePosixPath(parent).parts:
                    current = f'{current}/{part}'.lstrip('/')
                    try:
                        ftp.mkd(current)
                    except ftplib.error_perm:
                        # Existing directories are normal; verify access explicitly.
                        ftp.cwd(current)
                        ftp.cwd(remote_root)
                created.add(parent)
            with (ROOT / relative).open('rb') as source:
                ftp.storbinary(f'STOR {relative}', source)
            print(f'Uploaded {relative}')
        ftp.quit()
    print(f'Deployed {len(files)} files. Server configuration and uploads preserved.')


if __name__ == '__main__':
    deploy()

