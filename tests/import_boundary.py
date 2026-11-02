"""The built-in PHP web server must not be accepted as the CLI importer."""
import pathlib,socket,subprocess,sys,time,urllib.request,urllib.error

root=pathlib.Path(__file__).resolve().parents[1]
php=sys.argv[1] if len(sys.argv)>1 else 'php'
with socket.socket() as listener:
    listener.bind(('127.0.0.1',0));port=listener.getsockname()[1]
flags=subprocess.CREATE_NO_WINDOW if sys.platform=='win32' else 0
server=subprocess.Popen([php,'-n','-S','127.0.0.1:'+str(port),'-t',str(root)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=flags)
try:
    deadline=time.monotonic()+15
    while time.monotonic()<deadline:
        try:
            response=urllib.request.urlopen('http://127.0.0.1:'+str(port)+'/import.php',timeout=1)
            status=response.status;body=response.read().decode();break
        except urllib.error.HTTPError as exc:
            status=exc.code;body=exc.read().decode();break
        except urllib.error.URLError:time.sleep(0.1)
    else:raise RuntimeError('PHP fixture server did not start')
    assert status==403,(status,body)
    assert 'only be run from command line' in body,body
    print('PHP cli-server is denied access to the importer before database setup')
finally:
    server.terminate()
    try:server.wait(timeout=5)
    except subprocess.TimeoutExpired:server.kill();server.wait()
