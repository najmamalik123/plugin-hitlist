import { NextResponse } from "next/server"
import fs from "fs"
import path from "path"

const VOTES_FILE = path.join(process.cwd(), "data", "votes.json")

export async function GET() {
  try {
    if (!fs.existsSync(VOTES_FILE)) {
      const csv = "Titel,Artiest,Jaar,Stemmen\n"
      return new NextResponse(csv, {
        headers: {
          "Content-Type": "text/csv",
          "Content-Disposition": 'attachment; filename="stemresultaten.csv"',
        },
      })
    }

    const data = fs.readFileSync(VOTES_FILE, "utf8")
    const votes = JSON.parse(data)

    let csv = "Titel,Artiest,Jaar,Stemmen\n"

    Object.entries(votes).forEach(([key, count]) => {
      const [title, artist, year] = key.split("|")
      csv += `"${title}","${artist}","${year}",${count}\n`
    })

    return new NextResponse(csv, {
      headers: {
        "Content-Type": "text/csv",
        "Content-Disposition": 'attachment; filename="stemresultaten.csv"',
      },
    })
  } catch (error) {
    return NextResponse.json({ error: "Failed to export results" }, { status: 500 })
  }
}
