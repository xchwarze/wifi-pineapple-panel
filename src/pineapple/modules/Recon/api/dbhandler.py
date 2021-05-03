import threading
import sqlite3
from sqlite3 import Error
import time
import json
import subprocess
import sys
import thread

EncryptionFields = {
    "WPA": 0x01,
    "WPA2": 0x02,
    "WEP": 0x04,
    "WPA_PAIRWISE_WEP40": 0x08,
    "WPA_PAIRWISE_WEP104": 0x10,
    "WPA_PAIRWISE_TKIP": 0x20,
    "WPA_PAIRWISE_CCMP": 0x40,
    "WPA2_PAIRWISE_WEP40": 0x80,
    "WPA2_PAIRWISE_WEP104": 0x100,
    "WPA2_PAIRWISE_TKIP": 0x200,
    "WPA2_PAIRWISE_CCMP": 0x400,
    "WPA_AKM_PSK": 0x800,
    "WPA_AKM_ENTERPRISE": 0x1000,
    "WPA_AKM_ENTERPRISE_FT": 0x2000,
    "WPA2_AKM_PSK": 0x4000,
    "WPA2_AKM_ENTERPRISE": 0x8000,
    "WPA2_AKM_ENTERPRISE_FT": 0x10000,
    "WPA_GROUP_WEP40": 0x20000,
    "WPA_GROUP_WEP104": 0x40000,
    "WPA_GROUP_TKIP": 0x80000,
    "WPA_GROUP_CCMP": 0x100000,
    "WPA2_GROUP_WEP40": 0x200000,
    "WPA2_GROUP_WEP104": 0x400000,
    "WPA2_GROUP_TKIP": 0x800000,
    "WPA2_GROUP_CCMP": 0x1000000
}



class DBHandler(threading.Thread):
    def __init__(self, group=None, target=None, name=None, args=(), kwargs=None):
        threading.Thread.__init__(self, group=group, target=target, name=name)
        self.args = args
        self.kwargs = kwargs

        self.database = self.args[1]
        self.scanID = int(self.args[2])

        self.accessPoints = {}
        self.realAPs = []
        self.unassociatedClients = []
        self.outOfRangeClients = {}
        return

    def createAPArray(self):
        self.accessPoints = {}
        cursor = self.conn.cursor()
        cursor.execute("SELECT ssid,bssid,encryption,channel,signal,wps,last_seen from aps WHERE scan_id = " + str(self.scanID) + ";")
        apRows = cursor.fetchall()

        for row in apRows:
            ap = {}
            ap["bssid"] = row[1]
            ap["ssid"] = row[0]
            ap["channel"] = row[3]
            ap["encryption"] = self.printEncryption(row[2])
            ap["wps"] = row[5]
            ap["lastSeen"] = row[6]
            ap["power"] = row[4]
            ap["clients"] = []
            self.accessPoints[row[1]] = ap

            self.realAPs = []
            for key, value in self.accessPoints.iteritems():
                self.realAPs.append(value)

    def createClientsArray(self):
        self.unassociatedClients = []
        self.outOfRangeClients = {}
        cursor = self.conn.cursor()
        cursor.execute("SELECT mac,bssid,last_seen from clients WHERE scan_id = " + str(self.scanID) + ";")

        clientRows = cursor.fetchall()

        for row in clientRows:
            if row[1] == "FF:FF:FF:FF:FF:FF":
                self.unassociatedClients.append({'mac': row[0], 'lastSeen': row[2]})
            elif row[1] in self.accessPoints:
                cliObj = {"mac": row[0], "lastSeen": row[2]}
                if cliObj not in self.accessPoints[row[1]]["clients"]:
                    self.accessPoints[row[1]]["clients"].append(cliObj)
            else:
                self.outOfRangeClients[row[0]] = {"bssid": row[1], "lastSeen": row[2]}

    def printEncryption(self, encryptionType):
        retStr = ""
        if (encryptionType == 0):
            return "Open"
        if (encryptionType & EncryptionFields["WEP"]):
            return "WEP"
        elif ((encryptionType & EncryptionFields["WPA"]) and (encryptionType & EncryptionFields["WPA2"])):
            retStr += "WPA Mixed "
        elif (encryptionType & EncryptionFields["WPA"]):
            retStr += "WPA "
        elif (encryptionType & EncryptionFields["WPA2"]):
            retStr += "WPA2 "

        if ((encryptionType & EncryptionFields["WPA2_AKM_PSK"]) or (encryptionType & EncryptionFields["WPA_AKM_PSK"])):
            retStr += "PSK "
        elif ((encryptionType & EncryptionFields["WPA2_AKM_ENTERPRISE"]) or (encryptionType & EncryptionFields["WPA_AKM_ENTERPRISE"])):
            retStr += "Enterprise "
        elif ((encryptionType & EncryptionFields["WPA2_AKM_ENTERPRISE_FT"]) or (encryptionType & EncryptionFields["WPA_AKM_ENTERPRISE_FT"])):
            retStr += "Enterprise FT "

        retStr += "("

        if ((encryptionType & EncryptionFields["WPA2_PAIRWISE_CCMP"]) or (encryptionType & EncryptionFields["WPA_PAIRWISE_CCMP"])):
            retStr += "CCMP "

        if ((encryptionType & EncryptionFields["WPA2_PAIRWISE_TKIP"]) or (encryptionType & EncryptionFields["WPA_PAIRWISE_TKIP"])):
            retStr += "TKIP "

        # Fix the code below - these never trigger. Make sure to set "return WEP" to retStr += WEP
        if ((encryptionType & EncryptionFields["WPA2_PAIRWISE_WEP40"]) or (encryptionType & EncryptionFields["WPA_PAIRWISE_WEP40"])):
            retStr += "WEP40 "

        if ((encryptionType & EncryptionFields["WPA2_PAIRWISE_WEP104"]) or (encryptionType & EncryptionFields["WPA_PAIRWISE_WEP104"])):
            retStr += "WEP104 "

        retStr = retStr[:-1]
        retStr += ")"
        return retStr


    def run(self):
        try:
            self.conn = sqlite3.connect(self.database)
        except Error as e:
            print(e)
            return

        while True:
            if "scanID" not in subprocess.check_output(['/usr/bin/pineap', '/tmp/pineap.conf', 'get_status']):
                for websocket in self.args[0]:
                    try:
                        websocket.send_message(json.dumps({"scan_complete": True}))
                    except Exception:
                        pass
                thread.interrupt_main()

            self.createAPArray()
            self.createClientsArray()
            returnDictionary = {}
            returnDictionary["ap_list"] = self.realAPs
            returnDictionary["unassociated_clients"] = self.unassociatedClients
            returnDictionary["out_of_range_clients"] = self.outOfRangeClients
            returnDictionary["scan_complete"] = False

            for websocket in self.args[0]:
                try:
                    websocket.send_message(json.dumps(returnDictionary))
                except Exception, e:
                    pass

            time.sleep(3)