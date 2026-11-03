"""Check the supplied mitigation as startup configuration, without real uploads."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PHP = sys.argv[1] if len(sys.argv) > 1 else "php"
CHECK = """
if ((string) get_cfg_var('session.upload_progress.enabled') !== '0'
    || (string) ini_get('session.upload_progress.enabled') !== '0') {
    fwrite(STDERR, "Upload progress mitigation was not loaded at startup\\n");
    exit(1);
}
if (!session_start()) { exit(2); }
$_SESSION['fixture'] = 'ordinary sessions still work';
if ($_SESSION['fixture'] !== 'ordinary sessions still work') { exit(2); }
if (!session_write_close()) { exit(3); }
"""

with tempfile.TemporaryDirectory(prefix="php-startup-test-") as directory:
    fixture = Path(directory)
    # Isolate configuration from the machine and keep session files disposable.
    env = dict(os.environ, PHP_INI_SCAN_DIR=directory)

    def run(ini):
        return subprocess.run(
            [PHP, "-c", str(ini), "-d", f'session.save_path="{fixture.as_posix()}"', "-r", CHECK],
            env=env, capture_output=True, text=True, timeout=15,
        )

    positive = run(ROOT / "deploy/php-security.ini")
    assert positive.returncode == 0, positive.stderr
    unsafe = fixture / "unmitigated.ini"
    unsafe.write_text("session.upload_progress.enabled = 1\n", encoding="utf-8")
    negative = run(unsafe)
    assert negative.returncode == 1, "The check must reject an unmitigated configuration"

print("Startup mitigation loaded; ordinary sessions work; negative control rejected")
