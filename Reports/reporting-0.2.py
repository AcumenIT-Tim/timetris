import mysql.connector
import datetime
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter

import smtplib
from os.path import basename
from email.mime.application import MIMEApplication
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import COMMASPACE, formatdate

def gen_report(c, x, y, data, head):
    #Sets font to bold if it is a header
    if head == 1:
        canvas.setFont('Helvetica-Bold', 12)
    c.drawString(x, y, data)

    canvas.setFont('Helvetica', 12)

def nokCounter(cursor, serial):
    #Selects NOK counts
    sql = "SELECT functionNOK, cosmeticNOK, traceabilityNOK FROM eol WHERE serial_number = '" + serial + "';"

    cursor.execute(sql)
    nok = cursor.fetchone()
    totalNOK = nok[0] + nok[1] + nok[2]

    return str(totalNOK)
def send_mail(send_from, send_to, subject, text, files=None,
              server="smtp.office365.com"):
    #assert isinstance(send_to, list)

    msg = MIMEMultipart()
    msg['From'] = send_from
    msg['To'] = send_to
    msg['Date'] = formatdate(localtime=True)
    msg['Subject'] = subject

    msg.attach(MIMEText(text))

    with open(files, "rb") as fil:
        part = MIMEApplication(
            fil.read(),
            Name=basename(files)
        )
    # After the file is closed
    part['Content-Disposition'] = 'attachment; filename="%s"' % basename(files)
    msg.attach(part)


    smtp = smtplib.SMTP(server)
    smtp.ehlo()
    smtp.starttls()
    smtp.login('reports@isriusa.com', 'Isri123!')
    smtp.sendmail(send_from, send_to, msg.as_string())
    smtp.close()
    smtp.quit
#________________________________________________________________________
#                                   MAIN
#________________________________________________________________________

#Create DB Connection
mydb = mysql.connector.connect(
    host="10.73.104.141",
    user="kromer",
    passwd="Start123!",
    database="commercial_vehicle_production"
)
mycursor = mydb.cursor()

#creates date usable for SQL
date = datetime.datetime.now()
today = "20" + (date.strftime("%y") + "-" + date.strftime("%m")) + "-" + date.strftime("%d")

sql="SELECT DISTINCT part_number FROM production WHERE DATE(end_time) = '" + today + "';"
mycursor.execute(sql)

myresult = mycursor.fetchall()
partNumber = []
#Transfers Part number to array for future use
for x in myresult:
    partNumber.append(x)

sql = "SELECT COUNT(serial_number) FROM production WHERE DATE(end_time)='" + today +"';"
mycursor.execute(sql)
myresult = mycursor.fetchone()
total = myresult[0]

filename = "L:\\110-Program Management\\Galesburg Transfer\\Production Reports\\production_report_" + today +".pdf"
canvas = canvas.Canvas(filename, pagesize = letter)
canvas.setLineWidth(.3)
canvas.setFont('Helvetica', 12)


report = "Daily Production Report - Commercial Vehicles"
canvas.setFont('Helvetica-Bold', 14)
gen_report(canvas, 30, 750, report, 0)
canvas.setFont('Helvetica', 12)
gen_report(canvas, 500, 750, today, 0)

# Starting X and Y values for the report
partx = 30
serialx = 45
opx = 120
timex = 250
nokCountx = 350
y = 715

for count in partNumber: #Adds part number
    # Count[0] is type 'unicode', need to convert to string
    gen_report(canvas, partx, y, str(count[0]), 1)
    gen_report(canvas, opx, y, "Operator", 1)
    gen_report(canvas, timex, y, "Prod Time (min)", 1)
    gen_report(canvas, nokCountx, y, "NOK Count", 1)

    y = y - 15
    sql = "SELECT serial_number, operator, production_time FROM production WHERE part_number='" + count[0] + "' AND DATE(end_time)='" + today + "';" 
    mycursor.execute(sql)
    myresult = mycursor.fetchall()

    i = 0
    for c in myresult: # Adds indvidual seats to the report
        gen_report(canvas, serialx, y, str(c[0]), 0)
        gen_report(canvas, opx, y, c[1], 0)
        gen_report(canvas, timex, y, str(c[2]), 0)
        gen_report(canvas, nokCountx, y, nokCounter(mycursor, c[0]), 0)

        i +=1
        y = y - 15
        if y < 35:
            canvas.showPage()
            y = 715
            canvas.setLineWidth(.3)
            canvas.setFont('Helvetica', 12)


    canvas.line(partx, (y + 13), (partx + 80), (y+13)) # Draws line under the serial #
    gen_report(canvas, (serialx + 20), y, str(i), 1) #displays count of each part number
    y = y - 20 

canvas.line(partx, (y+13), (partx + 100), (y+13))
total = "Total: " + str(total)
gen_report(canvas, partx, (y - 1), total, 1) #displays total # of seats built
canvas.save()
print("File saved to " + filename)

toaddr   = "productionreports@isriusa.com"
fromaddr = "reports@isriusa.com"
subject = "Commercial Vehicle Production Report - " + today
body = "Attached is the Commercial Vehicle Production Report for " + today

#send_mail(fromaddr, toaddr, subject, body, filename)


