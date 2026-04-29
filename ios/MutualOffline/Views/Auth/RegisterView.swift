import SwiftUI

struct RegisterView: View {
    @EnvironmentObject var auth: AuthStore
    @State private var name = ""
    @State private var email = ""
    @State private var password = ""
    @State private var confirm = ""
    @State private var submitting = false

    var canSubmit: Bool {
        !submitting && !name.isEmpty && email.contains("@") && password.count >= 8 && password == confirm
    }

    var body: some View {
        Form {
            Section("Create account") {
                TextField("Name", text: $name)
                    .textContentType(.name)
                TextField("Email", text: $email)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                    .autocorrectionDisabled()
                SecureField("Password", text: $password)
                SecureField("Confirm password", text: $confirm)
            }
            if let err = auth.lastError {
                Section { Text(err).foregroundStyle(.red) }
            }
            Section {
                Button {
                    Task {
                        submitting = true
                        await auth.register(name: name, email: email, password: password)
                        submitting = false
                    }
                } label: {
                    HStack {
                        Spacer()
                        if submitting { ProgressView() } else { Text("Sign Up") }
                        Spacer()
                    }
                }
                .disabled(!canSubmit)
            }
        }
        .navigationTitle("Sign Up")
    }
}
