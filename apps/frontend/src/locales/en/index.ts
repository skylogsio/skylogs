import table from "./components/table";
import wrapper from "./components/wrapper";
import global from "./global";
import page from "./page";
import auth from "./pages/auth/signIn";

//! To avoid name collision put the name of file as the root of your message file
export default {
  //global
  ...global,
  //pages
  ...page,
  ...auth,
  //components
  ...wrapper,
  ...table
} as const;
