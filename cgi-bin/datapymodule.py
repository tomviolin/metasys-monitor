#!/home/tomh/anaconda3/envs/bacpypes/bin/python3
import BAC0
import json
import os,sys
import pandas as pd
import numpy as np

from sqlalchemy import create_engine
import datetime

# determine dataset
if len(sys.argv) < 2:
    dataset=0
else:
    dataset=int(sys.argv[1])

CACHE_FILE =  f"/tmp/metasys{dataset:03d}.json"
CACHE_STAGE = f"/tmp/_metasys{dataset:03d}json"


print("Cache-Control: no-cache, no-store, must-revalidate")
header("Pragma: no-cache")
header("Expires: 0")
header("Location: /metasys/")

print("Content-type: text/json\n\n")

if False and os.path.exists(CACHE_FILE):
    print(open(CACHE_FILE,"r").read())
    sys.exit(0)

def hyphenToCamel(hstr):
    harr = hstr.split('-')
    for i in range(1,len(harr)):
        harr[i] = harr[i][0].upper() + harr[i][1:]
    return ''.join(harr)


def units_abbrev(u):

    if u is None:
        return 'nul'
    if u == "BACnet Error":
         return ""
    if u == hyphenToCamel("degrees-fahrenheit"):
         return "&deg;F"
    if u == hyphenToCamel("degrees-celsius"):
         return "&deg;C"
    if u == hyphenToCamel("inches-of-water"):
         return "\"H<sub>2</sub>O"
    if u == hyphenToCamel("cubic-feet-per-minute"):
         return "CFM"
    if u == hyphenToCamel("percent-relative-humidity"):
         return "%RH"
    if u == hyphenToCamel("percent"):
         return "%"
    if u == hyphenToCamel("us-gallons-per-minute"):
         return "gpm"
    if u == hyphenToCamel("pounds-force-per-square-inch"):
         return "psi"
    if u == hyphenToCamel("revolutions-per-minute"):
         return "rpm"
    if u == hyphenToCamel("amperes"):
         return "Amps"
    if u == hyphenToCamel("megawatt-hours"):
         return "MWh"
    if u == hyphenToCamel("kilowatt-hours"):
         return "KWh"
    if u == "undefined":
        return "undf"
    if type(u)=='undefined':
        return 'udf'
    open("/tmp/erp","a").write(f"{u}\n")
    return u

try:
    con = create_engine("mysql+pymysql://metasys:Meta56sys$$@waterdata.glwi.uwm.edu/metasys")
except Exception as e:
    print(e)
    sys.exit(1)

devicesdf = pd.read_sql_query("select * from devices", con)

#print(devicesdf)
BAC0.log_level('silence')
#bacnet = BAC0.lite(port=9999)
bacnet = BAC0.connect()

displaypointstable = f"display_points2_{dataset:02d}"

allOutput = []

allDisplayPoints = {}

allAnalogValues = []

for i in range(devicesdf.shape[0]):
    devid = devicesdf.loc[i,'device_id']
    ipadr = devicesdf.loc[i,'ip_address']
    #print ((devid,ipadr))
    displaypointsdf = pd.read_sql_query(f"""
    SELECT  dp.*, ap.obj_type, ot.object_id obj_type_num
    FROM    {displaypointstable} dp
    LEFT JOIN allpoints_postchange_2022 ap ON
        dp.object_name = ap.obj_name
    LEFT JOIN object_types ot ON
        ot.object_name = ap.obj_type
    WHERE device_id_final = {devid}
    ORDER BY heading,sortkey
    """,con)
    #print(displaypointsdf)
    rpmobjects = {}
    for j in range(displaypointsdf.shape[0]):
        objid = displaypointsdf.loc[j,'obj_id_final']
        objtype = hyphenToCamel(displaypointsdf.loc[j,'obj_type'])
        if objtype[:6] == "analog":
            allAnalogValues.append(displaypointsdf.loc[j,'object_name'])
            rpmobjects[f'{objtype}:{objid}'] = ['objectName','presentValue','units']
        else:
            rpmobjects[f'{objtype}:{objid}'] = ['objectName','presentValue']
        allDisplayPoints[displaypointsdf.loc[j,'object_name']] = json.loads(displaypointsdf.loc[j,:].to_json())
    if len(rpmobjects)==0:
        continue
    rpmarg = {
        'address': f'{ipadr}:47808',
        'objects': rpmobjects
    }
    #print("=== ARGUMENTS ===")
    #print(rpmarg)
    #print("=== reading ===")
    objects = bacnet.readMultiple(f'{ipadr}:47808',request_dict=rpmarg)
    recdate = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    obfix = { k[0]+":"+str(k[1]):objects[k] for k in objects }
    #print("==== objects =====")
    #print(json.dumps(obfix,indent=2))
    for k in obfix:
        objdict={}
        for l in obfix[k]:
            objdict[l[0]]=l[1]
        #print("==== object =====")
        #print(json.dumps(objdict,indent=2))
        objdict['recdate']=recdate
        allOutput.append(objdict)
#print(json.dumps(allOutput,indent=4))


adata = pd.DataFrame(allOutput)
adata.columns=['object_name','value','recdate','units']

value = adata['value']
value[value=='active'] = 1
value[value!= 1] = 0



adata.to_sql(name='display_points_data_log2',con=con,if_exists='append', index=False)

"""
    {
        "desc": "4:TRANE status",
        "value": "0.00",
        "alarm_type": "0",
        "alarm_name": "",
        "history": [
            "0.00"
        ],
        "hard_min": "active",
        "units": "",
        "data_index": 13961,
        "soft_max": "active",
        "cerr": "error",
        "soft_min": "active",
        "histtime": [
            "Wed 00:21"
        ],
        "hard_max": "active"
    },
"""

byobjname = { k['objectName']:k for k in allOutput }
#print(byobjname)

for objname in allDisplayPoints:
    if objname not in allDisplayPoints:
        continue
    elif objname not in byobjname:
        continue
    else:
        unitsval = byobjname[objname]['presentValue']
    if 'units' in byobjname[objname]:
        if isinstance(unitsval, float):
            unitsval=round(unitsval,2)
        allDisplayPoints[objname]['value'] = unitsval
        allDisplayPoints[objname]['units'] = units_abbrev(byobjname[objname]['units'])
    allDisplayPoints[objname]['desc'] = str(allDisplayPoints[objname]['priority']) + ':' + allDisplayPoints[objname]['description']
for aobjname in allAnalogValues:
    #print(f"getting history for {aobjname}...")
    histData = pd.read_sql_query(f"""
    SELECT  object_name, recdate, value 
    FROM display_points_data_log2
    WHERE object_name = '{aobjname}'
    ORDER BY recdate DESC
    LIMIT 500;""", con)
    dates = list(np.array(histData.loc[:,'recdate']).astype('str'))

    allDisplayPoints[aobjname]['history'] = list(np.array(histData.loc[:,'value']))
    allDisplayPoints[aobjname]['histtime'] = dates

sortorderdf = pd.read_sql_query(f"""
SELECT  dp.object_name
FROM    {displaypointstable} dp
ORDER BY heading,sortkey
""",con)



allOutputPoints = [ allDisplayPoints[k] for k in list(sortorderdf.object_name)  if k in allDisplayPoints  ]

json.dump(allOutputPoints,open(CACHE_STAGE,"w"),indent=4)
#os.rename(CACHE_STAGE,CACHE_FILE)
print(json.dumps(allOutputPoints,indent=4))
