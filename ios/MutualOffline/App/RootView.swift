import SwiftUI

struct RootView: View {
    @EnvironmentObject var auth: AuthStore
    @EnvironmentObject var deviceRegistration: DeviceRegistrationService

    var body: some View {
        Group {
            if !auth.isAuthenticated {
                NavigationStack {
                    LoginView()
                }
            } else if !deviceRegistration.isRegistered {
                PermissionSetupView()
            } else {
                NavigationStack {
                    HomeView()
                }
            }
        }
        .task {
            await auth.bootstrap()
            if auth.isAuthenticated {
                await deviceRegistration.ensureRegistered()
            }
        }
        .onChange(of: auth.isAuthenticated) { _, newValue in
            if newValue {
                Task { await deviceRegistration.ensureRegistered() }
            } else {
                deviceRegistration.reset()
            }
        }
    }
}
