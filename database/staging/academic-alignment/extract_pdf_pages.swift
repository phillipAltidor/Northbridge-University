import Foundation
import PDFKit
let files = Array(CommandLine.arguments.dropFirst())
var results: [[String:Any]] = []
for path in files {
 if let doc=PDFDocument(url:URL(fileURLWithPath:path)) {
  let pages=(0..<doc.pageCount).map {doc.page(at:$0)?.string ?? ""}
  results.append(["path":path,"pages":pages,"pageCount":doc.pageCount])
 } else {results.append(["path":path,"error":"Cannot open PDF"])}
}
let data=try JSONSerialization.data(withJSONObject:results,options:[.prettyPrinted,.sortedKeys])
FileHandle.standardOutput.write(data)
