# -- Set up PineAP configuration

touch /etc/config/pineap
uci add pineap config
uci set pineap.@config[0].autostart=0
uci set pineap.@config[0].karma='off'
uci set pineap.@config[0].beacon_interval='NORMAL'
uci set pineap.@config[0].beacon_response_interval='NORMAL'
uci set pineap.@config[0].beacon_responses='off'
uci set pineap.@config[0].capture_ssids='off'
uci set pineap.@config[0].broadcast_ssid_pool='off'
uci set pineap.@config[0].logging='off'
uci set pineap.@config[0].mac_filter='black'
uci set pineap.@config[0].ssid_filter='black'
uci set pineap.@config[0].connect_notifications='off'
uci set pineap.@config[0].disconnect_notifications='off'
uci set pineap.@config[0].ap_channel=11
uci set pineap.@config[0].pineap_interface='wlan1mon'
uci set pineap.@config[0].pineap_source_interface='wlan0'

uci set pineap.@config[0].pineap_mac='00:11:22:33:44:55'
uci set pineap.@config[0].target_mac='FF:FF:FF:FF:FF:FF'
uci set pineap.@config[0].recon_db_path='/tmp/recon.db'
uci set pineap.@config[0].hostapd_db_path='/tmp/log.db'
uci set pineap.@config[0].ssid_db_path='/etc/pineapple/pineapple.db'

uci set pineap.@config[0].pineape_passthrough='off'

uci commit pineap

exit 0
