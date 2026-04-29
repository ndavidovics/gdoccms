import SwiftUI

struct QRDisplayView: View {
    let payload: String

    var body: some View {
        Group {
            if let img = QRCodeImage.make(from: payload) {
                Image(uiImage: img)
                    .interpolation(.none)
                    .resizable()
                    .scaledToFit()
            } else {
                Text("Could not render QR")
                    .foregroundStyle(.red)
            }
        }
    }
}
