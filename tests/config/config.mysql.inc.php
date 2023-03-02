[database]
driver = mysql
host = localhost
username = ojs
password = ojs
name = ojs

persistent = On
debug = Off

[general]
installed = On
base_url = "http://pkp.sfu.ca/ojs"
session_cookie_name = OJSSID
session_lifetime = 30
scheduled_tasks = Off

date_format_short = "Y-m-d"
date_format_long = "F j, Y"
datetime_format_short = "Y-m-d h:i A"
datetime_format_long = "F j, Y - h:i A"
time_format = "h:i A"

; base_url[index] = http://www.myUrl.com
; base_url[myJournal] = http://www.myUrl.com/myJournal
; base_url[myOtherJournal] = http://myOtherJournal.myUrl.com

allowed_hosts = "[\"mydomain.org\"]"

[cache]
cache = file
memcache_hostname = localhost
memcache_port = 11211
web_cache = Off
web_cache_hours = 1

[i18n]
locale = en
client_charset = utf-8
connection_charset = utf8

[files]
files_dir = files
public_files_dir = public
umask = 0022

[finfo]
mime_database_path = /etc/magic.mime

[security]
force_ssl = Off
force_login_ssl = Off
session_check_ip = On
encryption = md5
allowed_html = "a[href|target|title],em,strong,cite,code,ul,ol,li[class],dl,dt,dd,b,i,u,img[src|alt],sup,sub,br,p"
salt = "YouMustSetASecretKeyHere!!"

[email]
; smtp = On
; smtp_server = mail.example.com
; smtp_port = 25
; smtp_auth = PLAIN
; smtp_username = username
; smtp_password = password
; allow_envelope_sender = Off
; default_envelope_sender = my_address@my_host.com
time_between_emails = 3600
max_recipients = 10
require_validation = Off
validation_timeout = 14
display_errors = On

[search]
min_word_length = 3
results_per_keyword = 500
; index[application/pdf] = "/usr/bin/pstotext %s"
; index[application/pdf] = "/usr/bin/pdftotext %s -"
; index[application/postscript] = "/usr/bin/pstotext %s"
; index[application/postscript] = "/usr/bin/ps2ascii %s"
; index[application/msword] = "/usr/bin/antiword %s"
; index[application/msword] = "/usr/bin/catdoc %s"

[oai]
oai = On
repository_id = ojs.pkp.sfu.ca

[interface]
items_per_page = 25
page_links = 10

[captcha]
captcha = off
captcha_on_register = on
captcha_on_comments = on
font_location = /usr/share/fonts/truetype/freefont/FreeSerif.ttf

[proxy]
; http_host = localhost
; http_port = 80
; proxy_username = username
; proxy_password = password

[debug]
show_stacktrace = On
