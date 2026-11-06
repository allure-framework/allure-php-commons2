export default {
  name: "Allure PHP Commons 2",
  output: "./out/allure-report",
  plugins: {
    testops: {
      options: {
        launchName: `Allure PHP Commons 2 GitHub actions run (${new Date().toISOString()})`,
      },
    },
  },
};
