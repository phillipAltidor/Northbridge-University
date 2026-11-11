// Read-only source extraction for Modern Campus print PDFs on macOS.
// Usage: swift extract_pdf_courses.swift file.pdf ... > extracted.json
import Foundation
import PDFKit
import AppKit
let pattern = "(?m)^(?:[•]\\s*)?([A-Z](?:\\s?[A-Z]){1,2})\\s+(\\d{4}[A-Z]?)\\s*[-–]\\s*(.+)$"
let re = try NSRegularExpression(pattern: pattern)
var results: [[String:Any]] = []
for path in CommandLine.arguments.dropFirst() {
 guard let doc=PDFDocument(url:URL(fileURLWithPath:path)) else {fatalError("Cannot read \(path)")}
 var pages:[String]=[]
 var headings:[(offset:Int,end:Int,code:String,title:String,page:Int)]=[]
 var offset=0
 for i in 0..<doc.pageCount {
  guard let a=doc.page(at:i)?.attributedString else {fatalError("Missing text page \(i+1): \(path)")}
  let s=a.string as NSString
  for m in re.matches(in:a.string,range:NSRange(location:0,length:s.length)) {
   guard let font=a.attribute(.font,at:m.range.location,effectiveRange:nil) as? NSFont,
         font.pointSize >= 11, font.fontName.lowercased().contains("bold") else {continue}
   let prefix=s.substring(with:m.range(at:1)).replacingOccurrences(of:" ",with:"")
   headings.append((offset+m.range.location,offset+NSMaxRange(m.range),prefix+s.substring(with:m.range(at:2)),s.substring(with:m.range(at:3)),i+1))
  }
  pages.append(a.string);offset+=s.length+2
 }
 let full=pages.joined(separator:"\n\n") as NSString
 var records:[[String:Any]]=[]
 for (i,h) in headings.enumerated() {
  let end=i+1<headings.count ? headings[i+1].offset : full.length
  records.append(["code":h.code,"title":h.title,"sourceStartPage":h.page,"sourceText":full.substring(with:NSRange(location:h.end,length:end-h.end)),"status":"extracted-needs-rule-review"])
 }
 results.append(["sourcePDF":path,"pageCount":doc.pageCount,"pages":pages,"records":records,"headingDetection":"Bold font at least 11pt; prerequisite cross-reference links excluded; alphanumeric suffixes retained."])
}
FileHandle.standardOutput.write(try JSONSerialization.data(withJSONObject:results,options:[.prettyPrinted,.sortedKeys]))
