import openpyxl

file_path = r's:\elasticcost\vm_spec_cloud_part\XpressAzure Price List v2.7 - TND.xlsx'
wb = openpyxl.load_workbook(file_path, data_only=True)
print("Sheet names:", wb.sheetnames)
