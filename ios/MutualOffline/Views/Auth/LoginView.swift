import SwiftUI

struct LoginView: View {
    @EnvironmentObject var auth: AuthStore
    @State private var email = ""
    @State private var password = ""
    @State private var submitting = false

    var canSubmit: Bool {
        !submitting && email.contains("@") && password.count >= 6
    }

    var body: some View {
        Form {
            Section("Sign In") {
                TextField("Email", text: $email)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                    .autocorrectionDisabled()
                SecureField("Password", text: $password)
            }
            if let err = auth.lastError {
                Section { Text(err).foregroundStyle(.red) }
            }
            Section {
                Button {
                    Task {
                        submitting = true
                        await auth.login(email: email, password: password)
                        submitting = false
                    }
                } label: {
                    HStack {
                        Spacer()
                        if submitting { ProgressView() } else { Text("Sign In") }
                        Spacer()
                    }
                }
                .disabled(!canSubmit)

                NavigationLink("Create account") {
                    RegisterView()
                }
            }
        }
        .navigationTitle("Mutual Offline")
    }
}

#Preview {
    NavigationStack { LoginView() }
        .environmentObject(AuthStore(api: APIClient(keychain: KeychainStore()), keychain: KeychainStore()))
}
