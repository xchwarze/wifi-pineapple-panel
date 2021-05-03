import WebSocketsHandler
import dbhandler
import SocketServer
import fcntl
import sys
import os
import wsauth

LOCKFILE = '/tmp/reconpp.lock'

def acquire_lockfile():
    with open(LOCKFILE, 'w') as f:
        try:
            fcntl.flock(f, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except Exception:
            return False
    return True

def release_lockfile():
    os.unlink(LOCKFILE)

def main():
    if len(sys.argv) < 3 or len(sys.argv) > 3:
        print("Usage: reconpp.py <database> <scanID>")
        sys.exit(1)
    
    if not acquire_lockfile():
        print("Failed to acquire lock file. Is reconpp already running?")
        sys.exit(1)

    wsauth.write_token()

    websockets = []
    running = True

    database = sys.argv[1]
    scanID = sys.argv[2]

    dbHandler = dbhandler.DBHandler(args=(websockets, database, scanID))
    dbHandler.setDaemon(True)

    SocketServer.ThreadingTCPServer.allow_reuse_address = 1
    server = SocketServer.ThreadingTCPServer(("", 1337), WebSocketsHandler.WebSocketsHandler)
    server.running = running
    server.websockets = websockets

    try:
        dbHandler.start()
        server.serve_forever()
        dbHandler.join()
    except KeyboardInterrupt:
        server.running = False
        server.server_close()
    finally:
        release_lockfile()
        wsauth.remove_token()


if __name__ == '__main__':
    main()
