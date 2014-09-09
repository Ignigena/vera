core = 7.x
api = 2

includes[common] = "common.make.inc"

projects[acquia_connector][subdir] = "platform"
projects[acquia_connector][version] = "2.14"
projects[acquia_connector][exclude] = TRUE

projects[memcache][subdir] = "platform"
projects[memcache][version] = "1.2"
projects[memcache][exclude] = TRUE
